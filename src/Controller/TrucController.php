<?php

namespace App\Controller;

use App\Entity\Message;
use App\Form\MessageTypeForm;
use App\Repository\MessageRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\UX\Turbo\TurboBundle;

final class TrucController extends AbstractController
{
    #[Route('/truc', name: 'app_truc')]
    public function index(Request $request, HubInterface $hub, EntityManagerInterface $manager, MessageRepository $messageRepository): Response
    {

        $message = new Message();
        $form = $this->createForm(MessageTypeForm::class, $message );
        $empty = clone $form;
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

//            $update = new Update(
//                topics: "chat",
//                data: $this->renderView('truc/messagestream.html.twig', [
//                    "message"=>$form->getData(),
//                ])
//            );
//            $hub->publish($update);
            $manager->persist($message);
            $manager->flush();


            $form = $empty;
//            if(TurboBundle::STREAM_FORMAT === $request->getPreferredFormat()) {
//                $request->setRequestFormat(TurboBundle::STREAM_FORMAT);
//                return $this->render('truc/messagestream.html.twig', [
//                    "message"=>$form->getData(),
//                ]);
//
//            }
//            $message = $form->getData();
//            return new Response($this->renderView('truc/messagestream.html.twig', [
//                'message' => $message,
//            ] ),status: 200,headers: ["Content-type"=>"text/vnd.turbo-stream.html"]
//        );
        }


        return $this->render('truc/index.html.twig', [
            'form' => $form,
            'messages' => $messageRepository->findAll(),
        ]);
    }


}
