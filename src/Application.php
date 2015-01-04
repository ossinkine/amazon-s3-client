<?php

use Symfony\Component\HttpFoundation\Request;

class Application extends Silex\Application
{
    /**
     * @inheritdoc
     * @param array $values
     */
    public function __construct(array $values = array())
    {
        $values['amazon_s3_client.service'] = $this->share(function (Application $app) {
            $token = $app['security']->getToken();

            return Aws\S3\S3Client::factory(array(
                'key'    => $token->getUsername(),
                'secret' => $token->getCredentials(),
            ));
        });

        $values['amazon_s3_client.controller'] = $this->share(function (Application $app) {
            return new \Controller\AmazonS3Controller($app['twig'], $app['amazon_s3_client.service']);
        });

        parent::__construct($values);

        $this->register(new Silex\Provider\TwigServiceProvider(), array(
            'twig.path'    => __DIR__.'/../views',
            'twig.options' => array(
                'debug'            => $this['debug'],
                'cache'            => __DIR__.'/../cache/twig',
                'strict_variables' => true,
            ),
        ));
        $this->register(new Silex\Provider\UrlGeneratorServiceProvider());
        $this->register(new Silex\Provider\SessionServiceProvider(), array(
            'session.storage.save_path' => __DIR__.'/../cache/session',
        ));
        $this->register(new Silex\Provider\SecurityServiceProvider(), array(
            'security.firewalls' => array(
                'login' => array(
                    'pattern' => '^/login$',
                ),
                'main' => array(
                    'form'   => true,
                    'logout' => true,
                ),
            ),
            'security.authentication_manager' => $this->share(function (Application $app) {
                $manager = new Symfony\Component\Security\Core\Authentication\AuthenticationProviderManager($app['security.authentication_providers'], false);
                $manager->setEventDispatcher($app['dispatcher']);

                return $manager;
            }),
            'security.authentication_provider.main.dao' => $this->share(function () {
                return new Security\Authentication\Provider\AmazonS3Provider();
            }),
        ));
        $this->register(new Silex\Provider\ServiceControllerServiceProvider());

        $this->get('/login', function (Application $app, Request $request) {
            return $app['twig']->render('login.html.twig', array(
                'error'         => $app['security.last_error']($request),
                'last_username' => $app['session']->get('_security.last_username'),
            ));
        });

        $this
            ->get('/{bucket}', 'amazon_s3_client.controller:indexAction')
            ->value('bucket', null)
            ->bind('index')
        ;
    }
}
