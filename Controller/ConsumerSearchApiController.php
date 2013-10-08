<?php

namespace Tms\Bundle\FaqBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Tms\Bundle\FaqBundle\Exception\ResponseNotFoundException;
use Tms\Bundle\FaqBundle\Entity\ConsumerSearch;

/**
 * Api controller.
 *
 * @Route("/consumerSearchs")
 */
class ConsumerSearchApiController extends Controller
{

   /**
    * Post a Consumer Search
    *
    * @Route(".{_format}", name="tms_faq_api_consumer-searchs_post", defaults={"_format"="json"})
    * @Method("POST")
    */
    public function postAction(Request $request)
    {
        $response_id = $request->request->get('response_id');
        $answerFound = $request->request->get('answerFound');
        $query = $request->request->get('query');
        $format = $request->getRequestFormat();
        $response = new Response();
        try{
            $consumerSearch = $this->get('tms_faq.manager')->addConsumerSearch($response_id, $answerFound,$query);
            $export = $this->get('idci_exporter.manager')->export(array($consumerSearch),
                $format
            );
            $response->setStatusCode(201);
            $response->setContent($export->getContent());
            $response->headers->set(
                'Content-Type',
                sprintf('%s; charset=UTF-8', $export->getContentType())
            );
        }
        catch(\Exception $e){
            $response->setStatusCode(404);
            $response->setContent($e->getMessage());
        }

        return $response;
    }
}