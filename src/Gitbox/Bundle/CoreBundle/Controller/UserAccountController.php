<?php

namespace Gitbox\Bundle\CoreBundle\Controller;

use Gitbox\Bundle\CoreBundle\Entity\UserAccount;
use Gitbox\Bundle\CoreBundle\Entity\UserDescription;
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
    public function indexAction()
    {
	    return array();
    }

    /** Tworzy widok z formularzem do zajerestrownia użytkownika.
     * @Route("User/Register")
     * @Template()
     */
    public function registerAction(Request $request)
    {
	    $user = new UserAccount();

	    $form = $this->createForm(new UserAccountType(), $user);

	    $form->handleRequest($request);

	    if($form->isValid()) {
		    return $this->forward('GitboxCoreBundle:UserAccount:registerSubmit', array(
					array(
						'user'   => $user
					)
		    ));
	    }

	    return $this->render('GitboxCoreBundle:UserAccount:register.html.twig', array(
		    'form' => $form->createView(),
	    ));
    }

    /**
     * @Template()
     */
    public function registerSubmitAction(UserAccount $userAccount)
    {
	    /**
	     * @TODO email verification
	     */
	    $em = $this->getDoctrine()->getManager();
	    $userGroup = $em->getRepository('\Gitbox\Bundle\CoreBundle\Entity\UserGroup', 1);
		 $userDescription = new UserDescription();
	    $userDescription->setHit(1);
	    $userDescription->setRegistrationDate(date('Y-m-d H;i:s', strtotime('now')));

	    $userAccount->setIdDescription($userDescription);
		 $userAccount->setIdGroup($userGroup);

	    $em->persist($userAccount);

	    return array('name' => $userAccount->getLogin());
    }

    /**
     * @Route("User/Login")
     * @Template()
     */
    public function loginAction()
    {
    }

    /**
     * @Route("User/LoginSubmit")
     * @Template()
     */
    public function loginSubmitAction()
    {
    }

}