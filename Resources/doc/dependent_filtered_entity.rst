ShtumiUsefulBundle - make typical things easier

Dependent filtered entity
=========================

.. image:: https://github.com/shtumi/ShtumiUsefulBundle/raw/master/Resources/doc/images/dependent_filtered_entity.png


Configuration
-------------

You should configure relationship between master and dependent fields for each pair:

*In this example master entity - AcmeDemoBundle:Country, dependent - AcmeDemoBundle:Region*

// app/config/config.yml

::

    shtumi_useful :
        dependent_filtered_entities:
            region_by_country:
                class: AcmeDemoBundle:Region
                parent_property: country
                property: title
                callback: filterBySomeField
                role: ROLE_USER
                no_result_msg: 'No regions found for that country'
                order_property: title
                order_direction: ASC

- **class** - Doctrine dependent entity.
- **role** - User role to use form type. Default: ``IS_AUTHENTICATED_ANONYMOUSLY``. It needs for security reason.
- **parent_property** - property that contains master entity with ManyToOne relationship
- **property** - Property that will be used as text in select box. Default: ``title``
- **callback** - Entity repository method name or FQCN and static method name which gets ``Doctrine\ORM\QueryBuilder`` instance and entity root alias string as parameters and returns modified ``QueryBuilder`` object. For example: ``someFilterMethod``, ``SomeClass::someStaticFilterMethod``. Can be callable array too.
- **no_result_msg** - text that will be used for select box where nothing dependent entities were found for selected master entity. Default ``No results were found``. You can translate this message in ``messages.{locale}.php`` files.
- **order_property** - property that used for ordering dependent entities in selec box. Default: ``id``
- **order_direction** - You can use:
   - ``ASC`` - (**default**)
   - ``DESC`` - LIKE '%value'


Usage
=====

Simple usage
------------

Master and dependent fields should be in form together.

::

    $formBuilder
        ->add(
            'country',
            'entity',
             [
                 'class'      => 'AcmeDemoBundle:Country',
                 'required'   => true,
                 'empty_value'=> '== Choose country ==',
             ]
         )
        ->add(
            'region',
            'shtumi_dependent_filtered_entity',
            [
                'entity_alias' => 'region_by_country',
                'empty_value'=> '== Choose region ==',
                'parent_field'=>'country',
            ]
        )
    ;

- **parent_field** - name of master field in your FormBuilder

Default options for Select2 fields
----------------------------------

::

    $formBuilder
        ->add(
            'country',
            'entity',
             [
                 'class'      => 'AcmeDemoBundle:Country',
                 'required'   => true,
                 'empty_value'=> '== Choose country ==',
             ]
         )
        ->add(
            'region',
            'shtumi_dependent_filtered_select2',
            [
                'entity_alias' => 'region_by_country',
                'empty_value'=> '== Choose region ==',
                'parent_field'=>'country',
                'preferred_value' => $region->getId(),
                'preferred_text' => $region->getName(),
            ]
        )
    ;

- **parent_field** - name of master field in your FormBuilder
- **preferred_value** - (optional) Value of option which will be used as default until user chose any other option. Must be used with **preferred_text**
- **preferred_text** - (optional) Text of option which will be used as default until user chose any other option. Must be used with **preferred_value**

Callback example
----------------------------------------------------------------------------------------------------

::

    # /app/config/config.yml
    shtumi_useful :
        dependent_filtered_entities:
            region_by_country:
                class: AcmeDemoBundle:Region
                parent_property: country
                property: title
                callback: filterByRemoved

If you using repository method in ``callback`` parameter (like "``filterByRemoved``") you must add this method to your entity repository:

::

    // \Vendor\Namespace\Repository\SomeEntityRepository
    public function filterByRemoved(Doctrine\ORM\QueryBuilder $qb, $alias)
    {
        $qb->andWhere($alias.'.isRemoved <> TRUE');
        return $qb;
    }

Or if you using FQCN with static method name (like "``SomeClass::filterByRemoved``") you must add static method:

::

    // \Vendor\Namespace\SomeClassWithStaticMethod
    public static function filterByRemoved(Doctrine\ORM\QueryBuilder $qb, $alias)
    {
        $qb->andWhere($alias.'.isRemoved <> TRUE');
        return $qb;
    }

Mutiple levels
--------------

You can configure multiple dependent filters:

// app/config/config.yml

::

    shtumi_useful :
        dependent_filtered_entities:
            region_by_country:
                class: AcmeDemoBundle:Region
                parent_property: country
                property: title
                role: ROLE_USER
                no_result_msg: 'No regions found for that country'
                order_property: title
                order_direction: ASC
            town_by_region:
                class: AcmeDemoBundle:Town
                parent_property: region
                property: title
                role: ROLE_USER
                no_result_msg: 'No towns found for that region'
                order_property: title
                order_direction: ASC

::

    $formBuilder
        ->add(
            'country',
             'entity',
              [
                'required' => true,
                'empty_value' => '== Choose country =='
              ]
        )
        ->add(
            'region',
            'shtumi_dependent_filtered_entity',
            [
                'entity_alias' => 'region_by_country',
                'empty_value' => '== Choose region ==',
                'parent_field' =>'country'
            ]
        )
        ->add(
            'town',
            'shtumi_dependent_filtered_entity',
            [
                'entity_alias' => 'town_by_region',
                'empty_value' => '== Choose town ==',
                'parent_field' =>'region'
            ]
        )

- **parent_field** - name of master field in your FormBuilder

You should load `JQuery <http://jquery.com>`_ to your views.