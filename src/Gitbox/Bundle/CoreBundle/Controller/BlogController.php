<?php

namespace Gitbox\Bundle\CoreBundle\Controller;


use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Cookie;
use Gitbox\Bundle\CoreBundle\Entity\Content;
use Gitbox\Bundle\CoreBundle\Entity\Menu;
use Gitbox\Bundle\CoreBundle\Form\Type\BlogPostType;


class BlogController extends Controller
{

    /**
     * Zwraca informację o uprawnieniach użytkownika.
     *
     * @param $login
     * @return mixed
     */
    private function getAccess($login) {
        $permissionsHelper = $this->container->get('permissions_helper');

        $hasAccess = $permissionsHelper->checkPermission($login);

        return $hasAccess;
    }

    /**
     * Walidacja poprawności loginu użytkownika.
     * Zwraca dane użytkownika z bazy, w przypadku gdy istnieje użytkownik o nazwie podanej w URL.
     * W innym przypadku zwraca błąd 404.
     *
     * @param $login
     * @return mixed
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     */
    private function validateUser($login) {
        $userHelper = $this->container->get('user_helper');

        $user = $userHelper->findByLogin($login);

        if (!$user) {
            throw $this->createNotFoundException('Nie znaleziono użytkownika o nazwie <b>' . $login . '</b>.');
        }

        return $user;
    }

    /**
     * Walidacja statusu modułu użytkownika.
     * Zwraca instancję moduleHelper-a w przypadku, gdy użytkownik posiada aktywowany moduł.
     * W innym przypadku zwraca błąd 404.
     *
     * @param $login
     * @return object
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     */
    private function validateUserModule($login) {
        $moduleHelper = $this->container->get('module_helper');
        $moduleHelper->init('GitBlog');

        if (!$moduleHelper->isModuleActivated($login)) {
            throw $this->createNotFoundException('Użytkownik <b>' . $login . '</b> nie posiada aktywowanego modułu.');
        }

        return $moduleHelper;
    }

    /**
     * Walidacja dostępu do aktywności użytkownika np. dodawanie/edycja wpisu.
     *
     * @param $login
     * @return mixed
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     */
    private function checkAccess($login) {
        $hasAccess = $this->getAccess($login);

        if (!$hasAccess) {
            $session = $this->container->get('session');
            $userId = $session->get('userId');

            if (!isset($userId)) {
                throw $this->createNotFoundException('Zaloguj się, aby mieć dostęp do tej aktywności.');
            }

            throw $this->createNotFoundException('Nie masz dostępu do tej aktywności.');
        }

        return $hasAccess;
    }

    /**
     * Akcja dla głównej strony bloga, wyświetlenie wszystkich wpisów
     *
     * @Route("/user/{login}/blog", name="user_blog")
     * @Template()
     */
    public function indexAction($login)
    {
        // walidacja dostępu
        $user = $this->validateUser($login);
        $this->validateUserModule($login);
        $hasAccess = $this->getAccess($login);

        $contentHelper = $this->container->get('blog_content_helper');

        // pobieranie żądania // TODO: pobranie parametrów GET `żądania` [wyszukiwarka]
        $request = $this->get('request');
        // pobranie parametrów GET
        $title = $request->get('title');

        // pobranie wszystkich wpisów z bazy
        $posts = $contentHelper->getContents($login, $title, 5, $request);

        // inicjalizacja odpowiedzi serwera
        $response = new Response();

        // aktualizacja licznika odwiedzin na podstawie `ciasteczek`
        for ($i = 0; $i < count($posts); $i++) {
            $hitCookie = $request->cookies->get('hit_' . $posts[$i]->getId());

            if (!isset($hitCookie)) {
                $postsToUpdate[] =& $posts[$i];
                $posts[$i]->setHit($posts[$i]->getHit() + 1);

                $cookie = new Cookie('hit_' . $posts[$i]->getId(), true, time() + 3600 * 3);
                $response->headers->setCookie($cookie);
            }
        }
        if (isset($postsToUpdate)) {
            $contentHelper->updateArray($postsToUpdate);
        }

        $response->send();

        // inicjalizacja flash baga
        if (isset($title)) {
            $postCount = count($posts);

            $session = $this->container->get('session');

            if ($postCount == 0) {
                $msg = 'Nie znaleziono wpisów.';
            } else if ($postCount == 1) {
                $msg = 'Znaleziono <b>' . $postCount . '</b> wpis.';
            } else if ($postCount <= 4) {
                $msg = 'Znaleziono <b>' . $postCount . '</b> wpisy.';
            } else {
                $msg = 'Znaleziono <b>' . $postCount . '</b> wpisów.';
            }

            $session->getFlashBag()->add('success', $msg);
        }

        return array(
            'user' => $user,
            'posts' => $posts,
            'hasAccess' => $hasAccess
        );
    }

    /**
     * Dodawanie nowego wpisu
     *
     * @Route("/user/{login}/blog/new", name="user_new_post")
     * @Template()
     */
    public function newAction(Request $request, $login)
    {
        // walidacja dostępu
        $user = $this->validateUser($login);
        $moduleHelper = $this->validateUserModule($login);
        $this->checkAccess($login);

        // utworzenie instancji wpisu
        $postContent = new Content();

        // formularz nowego wpisu
        $form = $this->createForm(new BlogPostType(), $postContent);
        $form->handleRequest($request);

        // walidacja formularza
        if ($form->isValid()) {
            $contentHelper = $this->container->get('blog_content_helper');
            $menuHelper = $this->container->get('menu_helper');
            $menu = $menuHelper->findByUserAndModule($user->getId(), 'GitBlog');

            $postContent->setIdUser($user->getId());
            $postContent->setCreateDate(new \DateTime('now'));
            $postContent->setLastModificationDate(new \DateTime('now'));
            $postContent->setIdMenu($menu);

            $contentHelper->insert($postContent);

            // aktualizacja statystyk
            $moduleHelper->setTotalContents($user->getId(), '+');

            // inicjalizacja flash baga
            $session = $this->container->get('session');
            $session->getFlashBag()->add('success', 'Dodano wpis <b>' . $postContent->getTitle() . '</b>');

            return $this->redirect(
                $this->generateUrl('user_show_post', array(
                    'login' => $user->getLogin(),
                    'id' => $postContent->getId()
                ))
            );
        }

        return array(
            'user' => $user,
            'form' => $form->createView(),
            'btnLabel' => 'Dodaj wpis'
        );
    }

    /**
     * Edytowanie wpisu
     *
     * @Route("/user/{login}/blog/{id}/edit", name="user_edit_post")
     * @Template()
     */
    public function editAction(Request $request, $login, $id)
    {
        // walidacja dostępu
        $user = $this->validateUser($login);
        $this->validateUserModule($login);
        $this->checkAccess($login);

        $contentHelper = $this->container->get('blog_content_helper');

        // pobranie wpisu z bazy
        $postContent = $contentHelper->getOneContent($id, $login);

        $form = $this->createForm(new BlogPostType(), $postContent);
        $form->handleRequest($request);

        // walidacja formularza
        if ($form->isValid()) {
            $postContent->setLastModificationDate(new \DateTime('now'));

            $contentHelper->update($postContent);

            // inicjalizacja flash baga
            $session = $this->container->get('session');
            $session->getFlashBag()->add('success', 'Pomyślnie zaktualizowano wpis');

            return $this->redirect(
                $this->generateUrl('user_show_post', array(
                    'login' => $user->getLogin(),
                    'id' => $postContent->getId()
                ))
            );
        }

        return array(
            'user' => $user,
            'form' => $form->createView(),
            'btnLabel' => 'Edytuj wpis',
            'post' => $postContent,
            'calledBy' => $request->get('calledBy')
        );
    }

    /**
     * Wyświetlenie pojedynczego wpisu o id = {id}
     *
     * @Route("/user/{login}/blog/{id}", name="user_show_post")
     * @Template()
     */
    public function showAction($login, $id)
    {
        // walidacja dostępu
        $user = $this->validateUser($login);
        $this->validateUserModule($login);
        $hasAccess = $this->getAccess($login);

        $contentHelper = $this->container->get('blog_content_helper');

        $postContent = $contentHelper->getOneContent($id, $login);
        // TODO: pobieranie treści komentarzy

        // pobieranie żądania
        $request = $this->get('request');
        // inicjalizacja odpowiedzi serwera
        $response = new Response();

        if (!$postContent) {
            throw $this->createNotFoundException('Niestety nie znaleziono wpisu.');
        }

        // aktualizacja licznika odwiedzin na podstawie `ciasteczka`
        $hitCookie = $request->cookies->get('hit_' . $postContent->getId());

        if (!isset($hitCookie)) {
            $postContent->setHit($postContent->getHit() + 1);
            $contentHelper->update($postContent);

            $cookie = new Cookie('hit_' . $postContent->getId(), true, time() + 3600 * 3);
            $response->headers->setCookie($cookie);
        }

        $response->send();

        return array(
            'user' => $user,
            'post' => $postContent,
            'hasAccess' => $hasAccess
        );
    }

    /**
     * Usunięcie wpisu o id = {id}
     *
     * @Route("/user/{login}/blog/{id}/remove", name="user_remove_post")
     * @Method({"GET"})
     */
    public function removeAction($id, $login) {
        // walidacja dostępu
        $this->checkAccess($login);

        $contentHelper = $this->container->get('blog_content_helper');

        $post = $contentHelper->getOneContent(intval($id), $login);

        $postTitle = $post->getTitle();

        $contentHelper->remove(intval($id));

        $moduleHelper = $this->container->get('module_helper');
        $moduleHelper->init('GitBlog');
        $userDescHelper = $this->container->get('user_description_helper');

        // aktualizacja statystyk
        $moduleHelper->setTotalContents($login, '-');
        $userDescHelper->updateUserScore($login, $post->getVoteUp(), $post->getVoteDown());

        // inicjalizacja flash baga
        $session = $this->container->get('session');
        $session->getFlashBag()->add('success', 'Usunięto wpis <b>' . $postTitle . '</b>');

        return $this->redirect(
            $this->generateUrl('user_blog', array(
                'login' => $login
            ))
        );
    }

}
