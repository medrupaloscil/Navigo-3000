<?php

namespace AppBundle\Controller;

use AppBundle\Entity\User;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Role\Role;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;

class DefaultController extends Controller
{
    /**
     * @Route("/", name="homepage")
     */
    public function indexAction(Request $request) {
        return $this->render('default/index.html.twig', [
            'base_dir' => realpath($this->getParameter('kernel.root_dir').'/..').DIRECTORY_SEPARATOR,
        ]);
    }

    public function connectionAction(Request $request) {

        $username = $request->request->get('_username');

        $error = null;

        if ($username != null) {
            $em = $this->getDoctrine()->getManager();
            $users = $em->getRepository("AppBundle:User");
            $user = $users->findOneBy(array("mail" => $username, "password" => sha1($request->request->get("_password"))));

            if ($user != null) {
                $token = new UsernamePasswordToken($user, $user->getPassword(), "public", $user->getRoles());
                $this->get("security.token_storage")->setToken($token);
                $event = new InteractiveLoginEvent($request, $token);
                $this->get("event_dispatcher")->dispatch("security.interactive_login", $event);
                return $this->redirectToRoute("dashboard");
            }
            $error = "Bad Credentials";
        }

        return $this->render('default/login.html.twig', array(
            'last_username' => $username,
            'error'         => array("message" => $error),
        ));
    }

    /**
     * @Route("/inscription", name="inscription")
     */
    public function inscriptionAction(Request $request) {

        $user = new User();

        $form = $this->createFormBuilder($user)
            ->add('firstname', TextType::class, array( "attr" => array('placeholder' => 'Firstname')))
            ->add('lastname', TextType::class, array( "attr" => array('placeholder' => 'Lastname')))
            ->add('mail', TextType::class, array( "attr" => array('placeholder' => 'Mail')))
            ->add('password', PasswordType::class, array( "attr" => array('placeholder' => 'Password')))
            ->add('save', SubmitType::class, array('label' => 'Sign up'))
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $user->setRoles(["ROLE_USERS"]);
            $user->setPassword(sha1($user->getPassword()));

            $em = $this->getDoctrine()->getManager();
            $em->persist($user);
            $em->flush();

            return $this->redirectToRoute('login');
        }

        return $this->render('default/inscription.html.twig', array(
            'form' => $form->createView(),
        ));
    }

    /**
     * @Route("/test-card", name="testCard")
     */
    public function testCardAction(Request $request) {

        $result = null;
        $card = null;
        $id = $request->request->get('card-id');

        if ($id != null) {
            $controller = new ApiController();
            $controller->container = $this->container;
            $response = $controller->validityAction($request);

            if ($response->getContent()["success"] == false) {
                $this->get('session')->getFlashBag()->add(
                    'info',
                    $response->getContent()["message"]
                );
            } else {
                $result = "success";
                $card = $response->getContent()["card"];
            }
        }

        return $this->render('default/test-card.html.twig', array(
            "result" => $result,
            "card" => $card
        ));
    }

    /**
     * @Route("/user/dashboard", name="dashboard")
     * @Security("has_role('ROLE_USERS')")
     */
    public function dashboardAction(Request $request) {

        $card = null;
        $id = $request->request->get('card-id');

        if ($id != null) {
            $controller = new ApiController();
            $controller->container = $this->container;
            $response = $controller->validityAction($request);

            if ($response->getContent()["success"] == false) {
                $this->get('session')->getFlashBag()->add(
                    'info',
                    $response->getContent()["message"]
                );
            } else {
                $card = $response->getContent()["card"];
            }
        } else if ($request->request->get('type') != null) {
            $controller = new ApiController();
            $controller->container = $this->container;
            $response = $controller->paymentAction($request);

            $this->get('session')->getFlashBag()->add(
                'info',
                $response->getContent()["message"]
            );
        }

        return $this->render('default/user/dashboard.html.twig', array(
            "card" => $card
        ));
    }

    /**
     * @Route("/user/profile", name="profile")
     * @Security("has_role('ROLE_USERS')")
     */
    public function profileAction(Request $request) {

        $controller = new ApiController();
        $controller->container = $this->container;
        $em = $this->getDoctrine()->getManager();
        $factures = $em->getRepository("AppBundle:Facture");
        $userFactures = $factures->findBy(array("userId" => $this->getUser()));

        if ($request->request->get('navigo') != null) {
            $response = $controller->linkCardToUserAction($request);
            $this->get('session')->getFlashBag()->add(
                'info',
                $response->getContent()["message"]
            );
        } else if ($request->request->get('password') != null) {
            $response = $controller->changePasswordAction($request, $this->getUser());
            $this->get('session')->getFlashBag()->add(
                'info',
                $response->getContent()["message"]
            );
        } else if (count($_FILES) > 0) {

            $response = $controller->uploadImageAction($request);
            $this->get('session')->getFlashBag()->add(
                'info',
                $response->getContent()["message"]
            );
        }

        return $this->render('default/user/profile.html.twig', array(
            "factures" => $userFactures
        ));
    }

    /**
     * @Route("/admin/users/{page}", name="users")
     * @Security("has_role('ROLE_ADMIN')")
     */
    public function usersAction(Request $request, $page = 0) {

        $em = $this->getDoctrine()->getManager();
        $path = null;

        if ($request->request->get("repo") != null) {
            $controller = new ApiController();
            $controller->container = $this->container;

            $response = $controller->csvExportAction($request);

            $path = $response->getContent()["path"];

            $this->get('session')->getFlashBag()->add(
                'info',
                $response->getContent()["message"]
            );
        }

        $offset = $page * 50;

        $sql = "SELECT * FROM navigo.user LIMIT 50 OFFSET $offset;";
        $stmt = $em->getConnection()->prepare($sql);
        $stmt->execute();
        $users = $stmt->fetchAll();

        $sql = "SELECT COUNT(*) as count FROM navigo.user";
        $stmt = $em->getConnection()->prepare($sql);
        $stmt->execute();
        $count = floor($stmt->fetchAll()[0]["count"]/50);

        return $this->render('default/admin/users.html.twig', array(
            "users" => $users,
            "count" => $count,
            "page" => $page,
            "path" => $path
        ));
    }

    /**
     * @Route("/admin/cards/{page}", name="cards")
     * @Security("has_role('ROLE_ADMIN')")
     */
    public function cardsAction(Request $request, $page = 0) {

        $em = $this->getDoctrine()->getManager();
        $path = null;

        if ($request->request->get("repo") != null) {
            $controller = new ApiController();
            $controller->container = $this->container;

            $response = $controller->csvExportAction($request);

            $path = $response->getContent()["path"];

            $this->get('session')->getFlashBag()->add(
                'info',
                $response->getContent()["message"]
            );
        }

        $offset = $page * 50;

        $sql = "SELECT * FROM navigo.card LIMIT 50 OFFSET $offset;";
        $stmt = $em->getConnection()->prepare($sql);
        $stmt->execute();
        $cards = $stmt->fetchAll();

        $sql = "SELECT COUNT(*) as count FROM navigo.card";
        $stmt = $em->getConnection()->prepare($sql);
        $stmt->execute();
        $count = floor($stmt->fetchAll()[0]["count"]/50);

        return $this->render('default/admin/cards.html.twig', array(
            "cards" => $cards,
            "count" => $count,
            "page" => $page,
            "path" => $path
        ));
    }
}
