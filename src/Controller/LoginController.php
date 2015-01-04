<?php

namespace Controller;

use Twig_Environment as Twig;

class LoginController
{
    /**
     * @var Twig
     */
    private $twig;

    public function __construct(Twig $twig)
    {
        $this->twig = $twig;
    }

    /**
     * @return string
     */
    public function indexAction()
    {
        return $this->twig->render('login.html.twig', array(
            'error'         => '', //$app['security.last_error']($request),
            'last_username' => '', //$app['session']->get('_security.last_username'),
        ));
    }
}
