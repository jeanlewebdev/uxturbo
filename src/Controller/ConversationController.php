<?php

namespace App\Controller;

use App\Entity\Conversation;
use App\Entity\Message;
use App\Entity\User;
use App\Form\MessageTypeForm;
use App\Repository\ConversationRepository;
use App\Repository\UserRepository;
use App\Service\MercureJwtGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Mercure\Authorization;
use Symfony\Component\Mercure\Discovery;
use Symfony\Component\Security\Core\Authorization\AuthorizationChecker;

final class ConversationController extends AbstractController
{
    #[Route('/conversation/openwith/{id}', name: 'app_conversation_openwith')]
    public function openwith(User $withWhom, ConversationRepository $conversationRepository, EntityManagerInterface $manager): Response
    {
        $conv = $conversationRepository->findOneByCouple($this->getUser(),$withWhom);

//        if(!$conv){
//            die('not found');
//        }else{
//            die('found');
//        }
        if(!$conv){
            $conversation  = new Conversation();
            $conversation->addParticipant($this->getUser());
            $conversation->addParticipant($withWhom);
            $manager->persist($conversation);
            $manager->flush();
            $theId = $conversation->getId();
        }else{
            $theId = $conv->getId();
        }

        return $this->redirectToRoute('app_conversation_open', ["id"=>$theId]);
    }

    #[Route('/conversation/open/{id}', name: 'app_conversation_open')]
    public function open(HubInterface $hub,
                         Conversation $conversation,
                         Request $request,
                         EntityManagerInterface $manager,
                        MercureJwtGenerator $jwtGenerator,
                        Discovery $discovery,
                        Authorization $authorization,
    ):Response
    {
        if(!$conversation){return $this->redirectToRoute('app_convs');}

        $message = new Message();
        $form = $this->createForm(MessageTypeForm::class, $message);
        $empty = clone $form;
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $message->setConversation($conversation);
            $message->setAuthor($this->getUser());
            $manager->persist($message);
            $manager->flush();
           // die("/conversations/".$conversation->getId());
            $update = new Update(
                topics: "conversations/".$conversation->getId(),
                data: $this->renderView('broadcast/Message.stream.html.twig', [
                    "message"=>$message,
                ]),
                private: true,
            );
            try{
                $hub->publish($update);
            }catch (\Exception $e){
                throw $e;
            }



            $form = $empty;

        }
        $toktok = $jwtGenerator->generateSubscriberJwt();

        $hubUrl = $hub->getPublicUrl();
      // // dd($hubUrl);
      //  $discovery->addLink($request);
       $c= $authorization->createCookie($request,[$hubUrl."?topic=conversations/".$conversation->getId()], [$hubUrl."?topic=conversations/".$conversation->getId()]);

        $response = $this->render('conversation/open.html.twig', [
            'conversation' => $conversation,
            'form' => $form,
            'toktok' => $toktok,
            'hubUrl' => $hubUrl,
        ]);
        $cookie = $jwtGenerator->generateSubscriberJwt();
        //dd($cookie);
        $response->headers->set('set-cookie', "mercureAuthorization=$cookie; Path=$hubUrl; HttpOnly;");
        return $response;
    }

//    #[Route('/conversation/write/{id}', name: 'app_conversation_write')]
//    public function write(Conversation $conversation,Request $request, EntityManagerInterface $manager, HubInterface $hub):Response
//    {
//        if (!$conversation) {
//            return $this->redirectToRoute('app_convs');
//        }
//
//        $message = new Message();
//        $form = $this->createForm(MessageTypeForm::class, $message);
//        $empty = clone $form;
//        $form->handleRequest($request);
//
//        if ($form->isSubmitted() && $form->isValid()) {
//
//
//            $message->setConversation($conversation);
//            $message->setAuthor($this->getUser());
//            $manager->persist($message);
//            $manager->flush();
//            $update = new Update(
//                topics: "/conversations/".$conversation->getId(),
//                data: $this->renderView('broadcast/Message.stream.html.twig', [
//                    "message"=>$message,
//                ])
//            );
//            $hub->publish($update);
//
//            $form = $empty;
////            if(TurboBundle::STREAM_FORMAT === $request->getPreferredFormat()) {
////                $request->setRequestFormat(TurboBundle::STREAM_FORMAT);
////                return $this->render('truc/messagestream.html.twig', [
////                    "message"=>$form->getData(),
////                ]);
////
////            }
////            $message = $form->getData();
////            return new Response($this->renderView('truc/messagestream.html.twig', [
////                'message' => $message,
////            ] ),status: 200,headers: ["Content-type"=>"text/vnd.turbo-stream.html"]
////        );
//        }
//
//        return $this->json('coucou');
//    }
    #[Route('/convs', name: 'app_convs')]
    #[Route('/', name: 'app_convs_home')]
    public function convs(UserRepository $userRepository,Request $request, HubInterface $hub, EntityManagerInterface $manager): Response
    {
        if(!$this->getUser()){return $this->redirectToRoute('app_login');}
        return $this->render('truc/convs.html.twig', [
            "users" => $userRepository->findAll(),
        ]);
    }

    #[Route('/test', name: 'app_test')]
    public function test(HubInterface $hub, UserRepository $userRepository, ConversationRepository $conversationRepository, EntityManagerInterface $manager):Response
    {
        $message = new Message();
        $message->setAuthor($userRepository->find(2));
        $message->setConversation($conversationRepository->find(1));
        $message->setContent("tretretretertretertret");
        $manager->persist($message);
        $manager->flush();
        $update = new Update(
            topics: "conversations/1",
            data: $this->renderView('broadcast/Message.stream.html.twig', [
                "message"=>$message,
            ]),
           // private: true,
        );
        $hub->publish($update);

        return $this->json("coucou");
    }
    #[Route('/test2', name: 'app_test2')]
    public function test2(MercureJwtGenerator $jwtGenerator):Response
    {
        return $this->json($jwtGenerator->generateSubscriberJwt());
    }
}
