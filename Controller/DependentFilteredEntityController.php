<?php

namespace Shtumi\UsefulBundle\Controller;

use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class DependentFilteredEntityController extends Controller
{

    public function getOptionsAction()
    {

        $em = $this->get('doctrine')->getManager();
        $request = $this->getRequest();
        $translator = $this->get('translator');

        $entity_alias = $request->get('entity_alias');
        $parent_id    = $request->get('parent_id');
        $empty_value  = $request->get('empty_value');

        $entities = $this->get('service_container')->getParameter('shtumi.dependent_filtered_entities');
        $entity_inf = $entities[$entity_alias];

        if ($entity_inf['role'] !== 'IS_AUTHENTICATED_ANONYMOUSLY'){
            if (false === $this->get('security.context')->isGranted( $entity_inf['role'] )) {
                throw new AccessDeniedException();
            }
        }

        $queryRootAlias = 'e';

        $qb = $this->getDoctrine()
                ->getRepository($entity_inf['class'])
                ->createQueryBuilder($queryRootAlias)
                ->where($queryRootAlias.'.'.$entity_inf['parent_property'].' = :parent_id')
                ->orderBy($queryRootAlias.'.'.$entity_inf['order_property'], $entity_inf['order_direction'])
                ->setParameter('parent_id', $parent_id);

        if (null !== $entity_inf['callback']) {
            $qb = $this->processQueryCallback($qb, $queryRootAlias, $entity_inf['class'], $entity_inf['callback']);
        }

        $results = $qb->getQuery()->getResult();

        if (empty($results)) {
            return new Response('<option value="">' . $translator->trans($entity_inf['no_result_msg']) . '</option>');
        }

        $html = '';
        if ($empty_value !== false)
            $html .= '<option value="">' . $translator->trans($empty_value) . '</option>';

        $getter =  $this->getGetterName($entity_inf['property']);

        foreach($results as $result)
        {
            if ($entity_inf['property'])
                $res = $result->$getter();
            else $res = (string)$result;

            $html = $html . sprintf("<option value=\"%d\">%s</option>",$result->getId(), $res);
        }

        return new Response($html);

    }


    public function getJSONAction()
    {
        /** @var \Doctrine\ORM\EntityManager $em */
        $em = $this->get('doctrine.orm.entity_manager');
        /** @var Request $request */
        $request = $this->get('request');

        $entity_alias = $request->get('entity_alias');
        $parent_id    = $request->get('parent_id');
        $empty_value  = $request->get('empty_value');

        $entities = $this->get('service_container')->getParameter('shtumi.dependent_filtered_entities');
        $entity_inf = $entities[$entity_alias];

        if ($entity_inf['role'] !== 'IS_AUTHENTICATED_ANONYMOUSLY'){
            if (false === $this->get('security.context')->isGranted( $entity_inf['role'] )) {
                throw new AccessDeniedException();
            }
        }

        $term = $request->get('term');
        $maxRows = $request->get('maxRows', 20);

        $like = '%' . $term . '%';

        $queryRootAlias = 'e';

        $property = $entity_inf['property'];
        if (!$entity_inf['property_complicated']) {
            $property = $queryRootAlias.'.'.$property;
        }

        $qb = $em->createQueryBuilder()
            ->select($queryRootAlias)
            ->from($entity_inf['class'], $queryRootAlias)
            ->where($queryRootAlias.'.'.$entity_inf['parent_property'].' = :parent_id')
            ->setParameter('parent_id', $parent_id)
            ->orderBy($queryRootAlias.'.'.$entity_inf['order_property'], $entity_inf['order_direction'])
            ->setParameter('like', $like )
            ->setMaxResults($maxRows)
        ;

        if ($entity_inf['case_insensitive']) {
            $qb->andWhere('LOWER(' . $property . ') LIKE LOWER(:like)');
        } else {
            $qb->andWhere($property . ' LIKE :like');
        }

        if (null !== $entity_inf['callback']) {
            $qb = $this->processQueryCallback($qb, $queryRootAlias, $entity_inf['class'], $entity_inf['callback']);
        }

        $results = $qb->getQuery()->getResult();

        $res = array();
        foreach ($results AS $r){
            $res[] = array(
                'id' => $r->getId(),
                'text' => (string)$r
            );
        }

        return new Response(json_encode($res));
    }

    /**
     * Processes the query builder through callback
     *
     * @param QueryBuilder $qb
     * @param string $alias Root alias for entity
     * @param string $className Entity class
     * @param callable $callback
     *
     * @return QueryBuilder
     */
    private function processQueryCallback(QueryBuilder $qb, $alias, $className, $callback)
    {
        if (!is_callable($callback, true)) {
            throw new \InvalidArgumentException('$callback must be callable');
        }

        if (is_string($callback) && false === strpos($callback, '::', 1)) {
            // Callback is method of entity repository

            if (empty($className)) {
                throw new \InvalidArgumentException('$className must not be empty if using repository method as callback');
            }

            $repository = $qb->getEntityManager()->getRepository($className);

            if (!method_exists($repository, $callback)) {
                throw new \InvalidArgumentException(sprintf(
                    '%s repository for entity %s has no %s() method',
                    get_class($repository),
                    $className,
                    $callback
                ));
            }

            return $this->callCallback([$repository, $callback], [$qb, $alias]);
        } else {
            // Callback is static method of class

            return $this->callCallback($callback, [$qb, $alias]);
        }
    }

    /**
     * Calls callback and do some checks
     *
     * @param callable $callable
     * @param array $parameters
     *
     * @return QueryBuilder
     *
     * @throws \LogicException
     * @throws \RuntimeException
     */
    private function callCallback($callable, array $parameters)
    {
        if (false !== ($result = call_user_func_array($callable, $parameters))) {
            if (!$result instanceof QueryBuilder) {
                throw new \LogicException(sprintf(
                    'call_user_func_array() for query callback must return an instance of Doctrine\ORM\QueryBuilder. %s (%s) returned instead.',
                    gettype($result),
                    is_object($result) ? get_class($result) : ''
                ));
            }

            return $result;
        }

        throw new \RuntimeException('call_user_func_array() processed with error (returned false).');
    }

    private function getGetterName($property)
    {
        $name = "get";
        $name .= mb_strtoupper($property[0]) . substr($property, 1);

        while (($pos = strpos($name, '_')) !== false){
            $name = substr($name, 0, $pos) . mb_strtoupper(substr($name, $pos+1, 1)) . substr($name, $pos+2);
        }

        return $name;

    }
}
