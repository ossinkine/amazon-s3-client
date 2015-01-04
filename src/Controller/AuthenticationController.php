<?php

namespace Controller;

use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Twig_Environment as Twig;

class AuthenticationController
{
    /**
     * @var Twig
     */
    private $twig;

    /**
     * @var string
     */
    private $cookieName;

    /**
     * @param Twig   $twig
     * @param string $cookieName
     */
    public function __construct(Twig $twig, $cookieName)
    {
        $this->twig = $twig;
        $this->cookieName = $cookieName;
    }

    /**
     * @return string
     */
    public function loginAction()
    {
        return $this->twig->render('login.html.twig', array(
            'error' => '',
        ));
    }

    /**
     * @param  Request          $request
     * @return RedirectResponse
     */
    public function authenticateAction(Request $request)
    {
        $response = new RedirectResponse($request->headers->get('referer'));
        $response->headers->setCookie(new Cookie($this->cookieName, json_encode($request->request->all())));

        return $response;
    }

    /**
     * @param  Request          $request
     * @return RedirectResponse
     */
    public function logoutAction(Request $request)
    {
        $response = new RedirectResponse($request->headers->get('referer'));
        $response->headers->clearCookie($this->cookieName);

        return $response;
    }
}
