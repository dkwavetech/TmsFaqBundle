<?php

/**
 *
 * @author:  Gabriel BONDAZ <gabriel.bondaz@idci-consulting.fr>
 * @author:  Pichet PUTH <pichet.puth@utt.fr>
 * @license: GPL
 *
 */

namespace Tms\Bundle\FaqBundle\Controller\Rest;

use Symfony\Component\HttpFoundation\Request;
use FOS\RestBundle\Controller\FOSRestController;
use FOS\RestBundle\Controller\Annotations\Route;
use FOS\RestBundle\Controller\Annotations\Patch;
use FOS\RestBundle\Controller\Annotations\QueryParam;
use FOS\RestBundle\Controller\Annotations\RequestParam;
use FOS\RestBundle\Util\Codes;
use JMS\Serializer\SerializationContext;
use Tms\Bundle\RestBundle\Formatter\AbstractHypermediaFormatter;

/**
 * Question API REST controller
 */
class ApiQuestionController extends FOSRestController
{
    /**
     * [GET] /questions
     * Retrieve a set of Question
     *
     * @QueryParam(name="faq_id", requirements="\d+", strict=true, nullable=true, description="(optional) Faq id")
     * @QueryParam(name="question_category_id", requirements="\d+", strict=true, nullable=true, description="(optional) Question category id")
     * @QueryParam(name="tags", array=true, nullable=true, description="(optional) Question tags" )
     * @QueryParam(name="limit", requirements="\d+", strict=true, nullable=true, description="(optional) Pagination limit")
     * @QueryParam(name="offset", requirements="\d+", strict=true, nullable=true, description="(optional) Pagination offset")
     * @QueryParam(name="page", requirements="\d+", strict=true, nullable=true, description="(optional) Page number")
     * @QueryParam(name="sort", array=true, nullable=true, description="(optional) Sort")
     *
     * @param integer $faq_id
     * @param string  $question_category_id
     * @param array   $tags
     * @param integer $limit
     * @param integer $offset
     * @param integer $page
     * @param array   $sort
     */
    public function getQuestionsAction(
        $faq_id               = null,
        $question_category_id = null,
        $tags                 = array(),
        $limit                = null,
        $offset               = null,
        $page                 = null,
        $sort                 = null
    )
    {
        $formatter =  $this->get('tms_rest.formatter.factory')
            ->create(
                'orm_collection',
                $this->getRequest()->get('_route'),
                $this->getRequest()->getRequestFormat()
            )
            ->setObjectManager(
                $this->get('doctrine.orm.entity_manager'),
                $this
                    ->get('tms_faq.manager.question')
                    ->getEntityClass()
            )
            ->setCriteria(array(
                'faq'        => $faq_id,
                'categories' => array('id' => $question_category_id)
            ))
            ->setSort($sort)
            ->setLimit($limit)
            ->setOffset($offset)
            ->setPage($page)
        ;

        if (isset($tags[0])) {
            // Create the ElasticSearch Query
            $queryParts = array();
            foreach ($tags as $tag) {
                $queryParts[] = sprintf('tags: %s', $tag);
            }
            $query = implode(" AND ", $queryParts);
            $data = $this->container
                ->get('tms_search.handler')
                ->search('tms_faq_question', $query)
            ;

            // Retrieve question id's from the search result
            $ids = array();
            foreach ($data['data'] as $question) {
                $ids[] = $question['id'];
            }

            if (isset($ids[0])) {
                /*
                $formatter->initQueryBuilder(
                    'findById',
                    'question',
                    array('id' => $ids)
                );
                */
            }
        }

        $view = $this->view(
            $formatter->format(),
            Codes::HTTP_OK
        );

        $serializationContext = SerializationContext::create()
            ->setGroups(array(
                AbstractHypermediaFormatter::SERIALIZER_CONTEXT_GROUP_COLLECTION
            ))
        ;
        $view->setSerializationContext($serializationContext);

        return $this->handleView($view);
    }

    /**
     * [GET] /questions/{id}
     * Retrieve an Question
     *
     * @Route(requirements={"id" = "\d+"})
     *
     * @param integer $id
     */
    public function getQuestionAction($id)
    {
        try {
            $view = $this->view(
                $this
                    ->get('tms_rest.formatter.factory')
                    ->create(
                        'item',
                        $this->getRequest()->get('_route'),
                        $this->getRequest()->getRequestFormat(),
                        $id
                    )
                    ->setObjectManager(
                        $this->get('doctrine.orm.entity_manager'),
                        $this
                            ->get('tms_faq.manager.question')
                            ->getEntityClass()
                    )
                    ->addEmbedded(
                        'faq',
                        'api_faq_question_get_question_faq'
                    )
                    ->addEmbedded(
                        'categories',
                        'api_faq_question_get_question_questioncategories'
                    )
                    ->addEmbedded(
                        'evaluations',
                        'api_faq_question_get_question_evaluations'
                    )
                    ->format()
                ,
                Codes::HTTP_OK
            );

            $serializationContext = SerializationContext::create()
                ->setGroups(array(
                    AbstractHypermediaFormatter::SERIALIZER_CONTEXT_GROUP_ITEM
                ))
            ;
            $view->setSerializationContext($serializationContext);

            return $this->handleView($view);

        } catch(NotFoundHttpException $e) {
            return $this->handleView($this->view(
                array(),
                $e->getStatusCode()
            ));
        }
    }

    /**
     * [GET] /questions/{id}/faq
     * Retrieve faq associated with question
     *
     * @Route(requirements={"id" = "\d+"})
     *
     * @QueryParam(name="limit", requirements="\d+", strict=true, nullable=true, description="(optional) Pagination limit")
     * @QueryParam(name="offset", requirements="\d+", strict=true, nullable=true, description="(optional) Pagination offset")
     * @QueryParam(name="page", requirements="\d+", strict=true, nullable=true, description="(optional) Page number")
     * @QueryParam(name="sort", array=true, nullable=true, description="(optional) Sort")
     *
     * @param integer $id
     * @param integer $limit
     * @param integer $offset
     * @param integer $page
     * @param array   $sort
     */
    public function getQuestionFaqAction(
        $id,
        $limit  = null,
        $offset = null,
        $page   = null,
        $sort   = null
    )
    {
        try {
            $view = $this->view(
                $this
                    ->get('tms_rest.formatter.factory')
                    ->create(
                        'orm_collection',
                        $this->getRequest()->get('_route'),
                        $this->getRequest()->getRequestFormat()
                    )
                    ->setObjectManager(
                        $this->get('doctrine.orm.entity_manager'),
                        $this
                            ->get('tms_faq.manager.faq')
                            ->getEntityClass()
                    )
                    ->addItemRoute(
                        $this
                            ->get('tms_faq.manager.faq')
                            ->getEntityClass(),
                        'api_faq_get_faq'
                    )
                    ->setCriteria(array(
                        'questions' => array(
                            'id' => $id
                        )
                    ))
                    ->setSort($sort)
                    ->setLimit($limit)
                    ->setOffset($offset)
                    ->setPage($page)
                    ->format()
                ,
                Codes::HTTP_OK
            );

            $serializationContext = SerializationContext::create()
                ->setGroups(array(
                    AbstractHypermediaFormatter::SERIALIZER_CONTEXT_GROUP_COLLECTION
                ))
            ;
            $view->setSerializationContext($serializationContext);

            return $this->handleView($view);

        } catch(NotFoundHttpException $e) {
            return $this->handleView($this->view(
                array(),
                $e->getStatusCode()
            ));
        }
    }

    /**
     * [GET] /questions/{id}/questioncategories
     * Retrieve question categories associated with question
     *
     * @Route(requirements={"id" = "\d+"})
     *
     * @QueryParam(name="limit", requirements="\d+", strict=true, nullable=true, description="(optional) Pagination limit")
     * @QueryParam(name="offset", requirements="\d+", strict=true, nullable=true, description="(optional) Pagination offset")
     * @QueryParam(name="page", requirements="\d+", strict=true, nullable=true, description="(optional) Page number")
     * @QueryParam(name="sort", array=true, nullable=true, description="(optional) Sort")
     *
     * @param integer $id
     * @param integer $limit
     * @param integer $offset
     * @param integer $page
     * @param array   $sort
     */
    public function getQuestionQuestioncategoriesAction(
        $id,
        $limit  = null,
        $offset = null,
        $page   = null,
        $sort   = null
    )
    {
        try {
            $view = $this->view(
                $this
                    ->get('tms_rest.formatter.factory')
                    ->create(
                        'orm_collection',
                        $this->getRequest()->get('_route'),
                        $this->getRequest()->getRequestFormat()
                    )
                    ->setObjectManager(
                        $this->get('doctrine.orm.entity_manager'),
                        $this
                            ->get('tms_faq.manager.question_category')
                            ->getEntityClass()
                    )
                    ->addItemRoute(
                        $this
                            ->get('tms_faq.manager.question_category')
                            ->getEntityClass(),
                        'api_faq_question_category_get_questioncategory'
                    )
                    ->setCriteria(array(
                        'questions' => array(
                            'id' => $id
                        )
                    ))
                    ->setSort($sort)
                    ->setLimit($limit)
                    ->setOffset($offset)
                    ->setPage($page)
                    ->format()
                ,
                Codes::HTTP_OK
            );

            $serializationContext = SerializationContext::create()
                ->setGroups(array(
                    AbstractHypermediaFormatter::SERIALIZER_CONTEXT_GROUP_COLLECTION
                ))
            ;
            $view->setSerializationContext($serializationContext);

            return $this->handleView($view);

        } catch(NotFoundHttpException $e) {
            return $this->handleView($this->view(
                array(),
                $e->getStatusCode()
            ));
        }
    }

    /**
     * [GET] /questions/{id}/evaluations
     * Retrieve evaluations of a question
     *
     * @Route(requirements={"id" = "\d+"})
     *
     * @QueryParam(name="limit", requirements="\d+", strict=true, nullable=true, description="(optional) Pagination limit")
     * @QueryParam(name="offset", requirements="\d+", strict=true, nullable=true, description="(optional) Pagination offset")
     * @QueryParam(name="page", requirements="\d+", strict=true, nullable=true, description="(optional) Page number")
     * @QueryParam(name="sort", array=true, nullable=true, description="(optional) Sort")
     *
     * @param integer $id
     * @param integer $limit
     * @param integer $offset
     * @param integer $page
     * @param array   $sort
     */
    public function getQuestionEvaluationsAction(
        $id,
        $limit  = null,
        $offset = null,
        $page   = null,
        $sort   = null
    )
    {
        try {
            $view = $this->view(
                $this
                    ->get('tms_rest.formatter.factory')
                    ->create(
                        'orm_collection',
                        $this->getRequest()->get('_route'),
                        $this->getRequest()->getRequestFormat()
                    )
                    ->setObjectManager(
                        $this->get('doctrine.orm.entity_manager'),
                        $this
                            ->get('tms_faq.manager.evaluation')
                            ->getEntityClass()
                    )
                    ->addItemRoute(
                        $this
                            ->get('tms_faq.manager.evaluation')
                            ->getEntityClass(),
                        'api_faq_evaluation_get_evaluation'
                    )
                    ->setCriteria(array(
                        'question' => array(
                            'id' => $id
                        )
                    ))
                    ->setSort($sort)
                    ->setLimit($limit)
                    ->setOffset($offset)
                    ->setPage($page)
                    ->format()
                ,
                Codes::HTTP_OK
            );

            $serializationContext = SerializationContext::create()
                ->setGroups(array(
                    AbstractHypermediaFormatter::SERIALIZER_CONTEXT_GROUP_COLLECTION
                ))
            ;
            $view->setSerializationContext($serializationContext);

            return $this->handleView($view);

        } catch(NotFoundHttpException $e) {
            return $this->handleView($this->view(
                array(),
                $e->getStatusCode()
            ));
        }
    }

    /**
     * [PATCH] /questions/{id}/yepnope/{value}
     * Update a question entity
     *
     * @patch("/questions/{id}/yepnope/{value}", requirements={"id" = "\d+", "value" = "(yep|nope)"})
     *
     * @param integer $id
     * @param integer $value
     */
    public function patchQuestionYepNopeAction($id, $value)
    {
        $entity = $this->get('tms_faq.manager.question')->findOneById($id);
        if (!$entity) {
            $view = $this->view(
                array('message' => sprintf(
                    'Not found Question entity %s',
                    $id
                )),
                Codes::HTTP_NOT_FOUND
            );

            return $this->handleView($view);
        }

        if ($value === "yep") {
            $entity->addYep();
        } else if ($value === "nope") {
            $entity->addNope();
        }

        $this->get('tms_faq.manager.question')->update($entity);

        return $this->handleView($this->view(array(), codes::HTTP_OK));
    }
}
