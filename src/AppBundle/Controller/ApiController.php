<?php
/**
 * Created by PhpStorm.
 * User: medrupaloscil
 * Date: 13/12/2016
 * Time: 15:13
 */

namespace AppBundle\Controller;

use AppBundle\Entity\Card;
use AppBundle\Entity\Facture;
use AppBundle\Entity\User;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\BrowserKit\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Validator\Constraints\Date;
use Symfony\Component\Validator\Constraints\DateTime;

class ApiController extends Controller
{
    /**
     * @var TokenStorageInterface
     */
    private $tokenStorage;

    /**
     * @var AuthenticationManagerInterface
     */
    private $authenticationManager;

    /**
     * @var string Uniquely identifies the secured area
     */
    private $providerKey;

    /**
     * @Route("/api/v1/connection", name="apiConnection")
     */
    public function apiConnectionAction(Request $request) {

        $authenticationUtils = $this->get('security.authentication_utils');

        $error = $authenticationUtils->getLastAuthenticationError();

        $lastUsername = $authenticationUtils->getLastUsername();


        return new Response(array('last_username' => $lastUsername,'error' => $error));
    }

    /**
     * @Route("/api/v1/validity", name="apiValidity")
     */
    public function validityAction(Request $request) {

        $id = $request->request->get('card-id');

        $em = $this->getDoctrine()->getManager();
        $cards = $em->getRepository('AppBundle:Card');

        $card = $cards->findOneBy(array('cardId' => $id));

        if ($card == null) {
            $response = new Response(array("success" => false, "message" => "There is no card with id: $id."));
        } else {
            $response = new Response(array("success" => true, "card" => $card));
        }

        return $response;
    }

    /**
     * @Route("/api/v1/linkCardToUser", name="apiLinkCardToUser")
     * @Security("has_role('ROLE_USERS')")
     */
    public function linkCardToUserAction(Request $request) {

        $navigo = $request->request->get("navigo");

        if ($navigo != null) {

            $em = $this->getDoctrine()->getManager();
            $cards = $em->getRepository('AppBundle:Card');

            $card = $cards->findOneBy(array('cardId' => $navigo));

            if ($card == null) {

                $card = new Card();
                $card->setCardId($navigo);
                $em->persist($card);
                $em->flush();

                $user = $this->getUser();
                $user->setCard($card);

                $em->persist($user);
                $em->flush();

                $response = new Response(array("message" => "User updated"));
            } else {
                $response = new Response(array("message" => "Card already in our Database, please contact an administrator to link your card."), 200);
            }
        } else {
            $response = new Response(array("message" => "Data Error"));
        }

        return $response;
    }

    /**
     * @Route("/api/v1/linkCardToUser", name="apiLinkCardToUser")
     * @Security("has_role('ROLE_USERS')")
     */
    public function changePasswordAction(Request $request, User $user) {

        $password = $request->request->get("password");

        if ($password != null) {

            if (strlen($password) < 8) {
                $response = new Response(array("message" => "Password must have at least 8 characters"), 200);
            } else {
                $em = $this->getDoctrine()->getManager();

                $user->setPassword(sha1($password));

                $em->persist($user);
                $em->flush();

                $response = new Response(array("message" => "Password Updated"), 200);
            }
        } else {
            $response = new Response(array("message" => "Data Error"));
        }

        return $response;
    }

    /**
     * @Route("/api/v1/payment", name="apiPayment")
     * @Security("has_role('ROLE_USERS')")
     */
    function paymentAction(Request $request) {

        /*$host = $this->container->getParameter('paypal_host');
        $username = $this->container->getParameter('paypal_user');
        $password = $this->container->getParameter('paypal_secret');

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $host);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_USERPWD, $username . ":" . $password);
        $response = curl_exec($ch);
        curl_close($ch);

        var_dump($response);*/

        $em = $this->getDoctrine()->getManager();
        $user = $this->getUser();
        $now = date_create("now");

        $card = $user->getCard();
        $date = $card->getValid();

        if ($date < $now) {
            $date = $now;
        }

        switch ($request->request->get("type")) {
            case "day":
                date_add($date, date_interval_create_from_date_string('1 days'));
                $amount = 10;
                break;
            case "week":
                date_add($date, date_interval_create_from_date_string('7 days'));
                $amount = 24;
                break;
            case "Month":
                date_add($date, date_interval_create_from_date_string('30 days'));
                $amount = 70;
                break;
            case "Year":
                date_add($date, date_interval_create_from_date_string('365 days'));
                $amount = 341;
                break;
            default:
                return new Response(array("message" => "Type Error"));
                break;
        }

        $card->setValid($date);

        $facture = new Facture();
        $facture->setAmount($amount);
        $facture->setUserId($user);
        $facture->setDate($now);

        $em->persist($facture);
        $em->persist($card);
        $em->flush();

        return new Response(array("message" => "Card reloaded"));
    }

    /**
     * @Route("/api/v1/exportAsCsv", name="apiCsvExport")
     * @Security("has_role('ROLE_ADMIN')")
     */
    function csvExportAction(Request $request) {

        $em = $this->getDoctrine()->getManager();

        $repo = strtolower($request->request->get("repo"));
        $path = $this->get('kernel')->getRootDir() . "/../web/csv/$repo.csv";
        if (file_exists($path)) {
            unlink($path);
        }
        $sql = "SELECT * FROM navigo.$repo INTO OUTFILE \"$path\" FIELDS TERMINATED BY ',' ENCLOSED BY '\"' LINES TERMINATED BY '\n';";
        $stmt = $em->getConnection()->prepare($sql);
        $stmt->execute();

        return new Response(array("message" => "CSV created", "path" => $request->getUri()."/../../../csv/$repo.csv"));
    }

    /**
     * @Route("/api/v1/uploadImage", name="apiUploadImage")
     * @Security("has_role('ROLE_USERS')")
     */
    function uploadImageAction(Request $request) {

        if (count($_FILES) > 0) {
            $target_dir = $this->get('kernel')->getRootDir() . "/../web/cdn/";
            $target_file = $target_dir . basename($_FILES["file"]["name"]);
            $imageFileType = pathinfo($target_file,PATHINFO_EXTENSION);
            $uploadOk = 1;
            if(!empty($_FILES["file"]["tmp_name"])) {
                $check = getimagesize($_FILES["file"]["tmp_name"]);
                if($check === false) $uploadOk = 0;
            } else {
                $uploadOk = 0;
            }
            if (file_exists($target_file)) $uploadOk = 0;
            if ($_FILES["file"]["size"] > 1000000) $uploadOk = 0;
            if($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg" && $imageFileType != "gif" ) $uploadOk = 0;
            if ($uploadOk == 0) {
                $response = new Response(array("message" => "An error happend"));
            } else {
                if (move_uploaded_file($_FILES["file"]["tmp_name"], $target_file)) {

                    $em = $this->getDoctrine()->getManager();
                    $user = $this->getUser();
                    $final_file = 'cdn/'. basename($_FILES["file"]["name"]);
                    $user->setPicture($final_file);
                    $em->persist($user);
                    $em->flush();

                    $response = new Response(array("message" => "File uploaded"));
                } else {
                    $response = new Response(array("message" => "Error when moving file"));
                }
            }
        } else {
            $response = new Response(array("message" => "No file"));
        }

        return $response;
    }
}