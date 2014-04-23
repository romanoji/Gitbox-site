<?php

namespace Gitbox\Bundle\CoreBundle\Controller;

use Gitbox\Bundle\CoreBundle\Entity\UserAccount;
use Gitbox\Bundle\CoreBundle\Entity\UserDescription;
use Gitbox\Bundle\CoreBundle\Form\Type\UserAccountLoginType;
use Gitbox\Bundle\CoreBundle\Form\Type\UserAccountType;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;


class UserAccountController extends Controller
{
    /**
     * @Template()
     */
    public function indexAction(Request $request)
    {
	    $userAccount = new UserAccount();

	    $form = $this->createForm(new UserAccountLoginType(), $userAccount);

	    $form->handleRequest($request);

	    if($form->isValid()) {
			var_dump($userAccount);
	    }

	    return $this->render('GitboxCoreBundle:UserAccount:index.html.twig', array(
		    'form' => $form->createView(),
	    ));
    }

    /** Tworzy widok z formularzem do zajerestrownia użytkownika.
     * @Route("user/register")
     * @Template()
     */
    public function registerAction(Request $request)
    {
        $userAccount = new UserAccount();

        $form = $this->createForm(new UserAccountType(), $userAccount);

        $form->handleRequest($request);

        if($form->isValid()) {

	        $em = $this->getDoctrine()->getManager();
	        /**
	         * @param $userGroup \Gitbox\Bundle\CoreBundle\Entity\UserGroup
	         */
	        $userGroup = $em->getRepository('\Gitbox\Bundle\CoreBundle\Entity\UserGroup')->findOneBy(array('permissions' => 1));
	        $userDescription = new UserDescription();
	        $userDescription->setHit(1);
	        $date = new \DateTime();
	        $userDescription->setRegistrationDate($date);
			$userDescription->setBanDate(null);

	        $userAccount->setStatus('A');
	        /**
	         * @TODO email verification, ustawic status na 'D' i dopiero po wpisaniu tokena zmienic status na A
	         * $userDescription->setToken()
	         */

	        $em->persist($userDescription);

	        $userAccount->setIdDescription($userDescription);
	        $userAccount->setIdGroup($userGroup);

	        $em->persist($userAccount);
	        $em->flush();

            return $this->forward('GitboxCoreBundle:UserAccount:registerSubmit', array(
                'userName' => $userAccount->getLogin()
            ));

        }

        return $this->render('GitboxCoreBundle:UserAccount:register.html.twig', array(
            'form' => $form->createView(),
        ));
    }

    /**
     * @Template()
     */
    public function registerSubmitAction($userName)
    {
	    return array('name' => $userName);
    }

    /**
     * @Route("user/login")
     * @Template()
     */
    public function loginAction()
    {
	    $userAccount = new UserAccount();

	    $form = $this->createForm(new UserAccountType(), $userAccount);

	    return $this->render('GitboxCoreBundle:UserAccount:login.html.twig', array(
		    'form' => $form->createView(),
	    ));
    }

    /**
     * @Route("user/login/submit")
     * @Template()
     */
    public function loginSubmitAction()
    {
	    return array();
    }

	/**
	 * @Route("user/getMyPasswordBack")
	 * @Template()
	 */
	public function forgottenPasswordAction() {
		return array();
	}

    /**
     * @Route("user/{login}")
     * @Template()
     */
    public function showAction($login) {
        $user['login'] = $login;

        return array('user' => $user);
    }


}
