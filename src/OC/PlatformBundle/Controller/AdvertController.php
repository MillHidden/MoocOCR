<?php

// src/OC/PlatformBundle/Controller/AdvertController.php

namespace OC\PlatformBundle\Controller;

use OC\PlatformBundle\Entity\Advert;
use OC\PlatformBundle\Form\AdvertType;
use OC\PlatformBundle\Form\AdvertEditType;
use OC\PlatformBundle\Event\PlatformEvents;
use OC\PlatformBundle\Event\MessagePostEvent;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;

class AdvertController extends Controller
{
  public function indexAction($page)
  {
    if ($page < 1) {
      throw new NotFoundHttpException('Page "'.$page.'" inexistante.');
    }

    // Ici je fixe le nombre d'annonces par page à 3
    // Mais bien sûr il faudrait utiliser un paramètre, et y accéder via $this->container->getParameter('nb_per_page')
    $nbPerPage = 3;

    // On récupère notre objet Paginator
    $listAdverts = $this->getDoctrine()
      ->getManager()
      ->getRepository('OCPlatformBundle:Advert')
      ->getAdverts($page, $nbPerPage)
    ;

    // On calcule le nombre total de pages grâce au count($listAdverts) qui retourne le nombre total d'annonces
    $nbPages = ceil(count($listAdverts) / $nbPerPage);

    // Si la page n'existe pas, on retourne une 404
    if ($page > $nbPages) {
      throw $this->createNotFoundException("La page ".$page." n'existe pas.");
    }

    // On donne toutes les informations nécessaires à la vue
    return $this->render('OCPlatformBundle:Advert:index.html.twig', array(
      'listAdverts' => $listAdverts,
      'nbPages'     => $nbPages,
      'page'        => $page,
    ));
  }

  public function viewAction(Advert $advert)
  {
    $em = $this->getDoctrine()->getManager(); 

    // Récupération de la liste des candidatures de l'annonce
    $listApplications = $em
      ->getRepository('OCPlatformBundle:Application')
      ->findBy(array('advert' => $advert))
    ;

    // Récupération des AdvertSkill de l'annonce
    $listAdvertSkills = $em
      ->getRepository('OCPlatformBundle:AdvertSkill')
      ->findBy(array('advert' => $advert))
    ;

    return $this->render('OCPlatformBundle:Advert:view.html.twig', array(
      'advert'           => $advert,
      'listApplications' => $listApplications,
      'listAdvertSkills' => $listAdvertSkills,
    ));
  }

  
  public function addAction(Request $request)
  { 
    $advert = new Advert();
    $advert->setUser($this->getUser());

    $form = $this
      ->get('form.factory')
      ->create(AdvertType::class, $advert)
    ;   

    if ($request->isMethod('POST') && $form->handleRequest($request)->isValid()) {
      
      $event = new MessagePostEvent($advert->getContent(), $advert->getUser());

      $this
        ->get('event_dispatcher')
        ->dispatch(PlatformEvents::POST_MESSAGE, $event)
      ;

      $advert->setContent($event->getMessage());

      $em = $this->getDoctrine()->getManager();
      $em->persist($advert);
      $em->flush();

      $request
        ->getSession()
        ->getFlashBag()
        ->add('notice', 'Annonce bien enregistrée.')
      ;

      return $this->redirectToRoute('oc_platform_view', array('id' => $advert->getId()));
    }
    
    return $this->render('OCPlatformBundle:Advert:add.html.twig', array(
      'form' => $form->createView(),
    ));
  }

  public function editAction($id, Request $request)
  {
    $em = $this->getDoctrine()->getManager();

    $advert = $em->getRepository('OCPlatformBundle:Advert')->find($id);

    if (null === $advert) {
      throw new NotFoundHttpException("L'annonce d'id ".$id." n'existe pas.");
    }

    $form = $this
      ->get('form.factory')
      ->create(AdvertEditType::class, $advert)      
    ;    

    if ($request->isMethod('POST') && $form->handleRequest($request)->isValid()) {
      $em->flush();

      $request->getSession()->getFlashBag()->add('notice', 'Annonce bien modifiée.');

      return $this->redirectToRoute('oc_platform_view', array('id' => $advert->getId()));
    }

    return $this->render('OCPlatformBundle:Advert:edit.html.twig', array(
      'advert'  => $advert,
      'form'    => $form->createView(),
    ));
  }

  public function deleteAction(Request $request, $id)
  {
    $em = $this->getDoctrine()->getManager();

    $advert = $em->getRepository('OCPlatformBundle:Advert')->find($id);

    if (null === $advert) {
      throw new NotFoundHttpException("L'annonce d'id ".$id." n'existe pas.");
    }

    $form = $this
      ->get('form.factory')
      ->create()      
    ;

    if ($request->isMethod('POST') && $form->handleRequest($request)->isValid()) {
      $em->remove($advert);
      $em->flush();

      $request->getSession()->getFlashBag()->add('notice', "L'annonce a bien été supprimée.");

      return $this->redirectToRoute('oc_platform_home');
    }    
    
    return $this->render('OCPlatformBundle:Advert:delete.html.twig', array(
      'advert' => $advert,
      'form'   => $form->createView()
    ));
  }

  public function menuAction($limit)
  {
    $em = $this->getDoctrine()->getManager();

    $listAdverts = $em->getRepository('OCPlatformBundle:Advert')->findBy(
      array(),                 // Pas de critère
      array('date' => 'desc'), // On trie par date décroissante
      $limit,                  // On sélectionne $limit annonces
      0                        // À partir du premier
    );

    return $this->render('OCPlatformBundle:Advert:menu.html.twig', array(
      'listAdverts' => $listAdverts
    ));
  }

  // Méthode facultative pour tester la purge
  public function purgeAction($days, Request $request)
  {
    // On récupère notre service
    $purger = $this->get('oc_platform.purger.advert');

    // On purge les annonces
    $purger->purge($days);

    // On ajoute un message flash arbitraire
    $request->getSession()->getFlashBag()->add('info', 'Les annonces plus vieilles que '.$days.' jours ont été purgées.');

    // On redirige vers la page d'accueil
    return $this->redirectToRoute('oc_platform_home');
  }

  public function testAction()
  {
    $advert = new Advert;
        
    $advert->setDate(new \Datetime());  // Champ « date » OK
    $advert->setTitle('abcdefghijlkalde');           // Champ « title » incorrect : moins de 10 caractères
    //$advert->setContent('blabla');    // Champ « content » incorrect : on ne le définit pas
    $advert->setAuthor('ABgeft');            // Champ « author » incorrect : moins de 2 caractères
    $advert->setContent('blablablabla content');
    // On récupère le service validator
    $validator = $this->get('validator');
        
    // On déclenche la validation sur notre object
    $listErrors = $validator->validate($advert);

    // Si $listErrors n'est pas vide, on affiche les erreurs
    if(count($listErrors) > 0) {
      // $listErrors est un objet, sa méthode __toString permet de lister joliement les erreurs
      return new Response((string) $listErrors);
    } else {
      return new Response("L'annonce est valide !");
    }
  }

  public function translationAction($name)
  {
    return $this->render('OCPlatformBundle:Advert:translation.html.twig', array(
      'name' => $name
    ));
  }

  /**
   * @ParamConverter("json")
   */
  public function paramConverterAction($json)
  {
    return new Response(print_r($json, true));
  }
}
