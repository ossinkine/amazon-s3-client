<?php

use Aws\S3\S3Client;
use Controller\AmazonS3Controller;
use Controller\AuthenticationController;
use Silex\Provider\ServiceControllerServiceProvider;
use Silex\Provider\TwigServiceProvider;
use Silex\Provider\UrlGeneratorServiceProvider;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class Application extends Silex\Application
{
    /**
     * @inheritdoc
     * @param array $values
     */
    public function __construct(array $values = [])
    {
        $values['amazon_s3_client'] = $this->share(function (Application $app) {
            return S3Client::factory($app['amazon_s3_credentials']);
        });
        $values['amazon_s3_credentials'] = $this->share(function (Application $app) {
            return json_decode($app['request']->cookies->get($app['amazon_s3_credentials_cookie_name']), true);
        });
        $values['amazon_s3_credentials_cookie_name'] = 'credentials';

        $values['controller.amazon_s3_client'] = $this->share(function (Application $app) {
            return new AmazonS3Controller($app['twig'], $app['amazon_s3_client']);
        });
        $values['controller.authentication'] = $this->share(function (Application $app) {
            return new AuthenticationController($app['twig'], $app['amazon_s3_credentials_cookie_name']);
        });

        parent::__construct($values);

        $this->register(new TwigServiceProvider(), [
            'twig.path'    => __DIR__.'/../views',
            'twig.options' => [
                'debug'            => $this['debug'],
                'cache'            => __DIR__.'/../cache/twig',
                'strict_variables' => true,
            ],
        ]);
        $this->register(new UrlGeneratorServiceProvider());
        $this->register(new ServiceControllerServiceProvider());

        $this
            ->get('/login', 'controller.authentication:loginAction')
            ->bind('login')
            ->before(function (Request $request, Application $app) {
                if (!empty($app['amazon_s3_credentials'])) {
                    return new RedirectResponse($app['url_generator']->generate('list'));
                }
            })
        ;
        $this
            ->post('/login', 'controller.authentication:authenticateAction')
        ;
        $this
            ->post('/logout', 'controller.authentication:logoutAction')
            ->bind('logout')
        ;

        $this
            ->get('/{bucket}', 'controller.amazon_s3_client:listAction')
            ->value('bucket', null)
            ->bind('list')
            ->before(function (Request $request, Application $app) {
                if (empty($app['amazon_s3_credentials'])) {
                    return $app->handle(
                        Request::create($app['url_generator']->generate('login')),
                        HttpKernelInterface::SUB_REQUEST,
                        false
                    );
                }
            })
        ;
    }
}
