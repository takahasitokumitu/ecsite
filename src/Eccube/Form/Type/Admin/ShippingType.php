<?php
/*
 * This file is part of EC-CUBE
 *
 * Copyright(c) 2000-2015 LOCKON CO.,LTD. All Rights Reserved.
 *
 * http://www.lockon.co.jp/
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 */


namespace Eccube\Form\Type\Admin;

use Doctrine\ORM\EntityRepository;
use Eccube\Common\Constant;
use Eccube\Form\Type\AddressType;
use Eccube\Form\Type\KanaType;
use Eccube\Form\Type\NameType;
use Eccube\Form\Type\TelType;
use Eccube\Form\Type\ZipType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class ShippingType extends AbstractType
{
    public $app;

    public function __construct(\Eccube\Application $app)
    {
        $this->app = $app;
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $app = $this->app;
        $config = $app['config'];
        $BaseInfo = $app['eccube.repository.base_info']->get();

        $builder
            ->add('name', NameType::class, array(
                'required' => false,
                'options' => array(
                    'constraints' => array(
                        new Assert\NotBlank(),
                    ),
                ),
            ))
            ->add('kana', KanaType::class, array(
                'required' => false,
                'options' => array(
                    'constraints' => array(
                        new Assert\NotBlank(),
                    ),
                ),
            ))
            ->add('company_name', TextType::class, array(
                'label' => '会社名',
                'required' => false,
                'constraints' => array(
                    new Assert\Length(array(
                        'max' => $config['stext_len'],
                    ))
                ),
            ))
            ->add('zip', ZipType::class, array(
                'required' => false,
                'options' => array(
                    'constraints' => array(
                        new Assert\NotBlank(),
                    ),
                ),
            ))
            ->add('address', AddressType::class, array(
                'required' => false,
                'pref_options' => array(
                    'constraints' => array(
                        new Assert\NotBlank(),
                    ),
                ),
                'addr01_options' => array(
                    'constraints' => array(
                        new Assert\NotBlank(),
                        new Assert\Length(array(
                            'max' => $config['mtext_len'],
                        )),
                    ),
                ),
                'addr02_options' => array(
                    'required' => false,
                    'constraints' => array(
                        new Assert\NotBlank(),
                        new Assert\Length(array(
                            'max' => $config['mtext_len'],
                        )),
                    ),
                ),
            ))
            ->add('tel', TelType::class, array(
                'required' => false,
                'options' => array(
                    'constraints' => array(
                        new Assert\NotBlank(),
                    ),
                ),
            ))
            ->add('fax', TelTYpe::class, array(
                'label' => 'FAX番号',
                'required' => false,
            ))
            ->add('Delivery', EntityType::class, array(
                'required' => false,
                'label' => '配送業者',
                'class' => 'Eccube\Entity\Delivery',
                'choice_label' => 'name',
                'placeholder' => '選択してください',
                // 'empty_data' => null,
                'constraints' => array(
                    new Assert\NotBlank(),
                ),
            ))
            ->add('shipping_delivery_date', DateType::class, array(
                'label' => 'お届け日',
                'placeholder' => '',
                'format' => 'yyyy-MM-dd',
                'required' => false,
            ))
            ->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use ($BaseInfo) {
                if ($BaseInfo->getOptionMultipleShipping() == Constant::ENABLED) {
                    $form = $event->getForm();
                    $form->add('ShipmentItems', CollectionType::class, array(
                        'type' => 'shipment_item',
                        'allow_add' => true,
                        'allow_delete' => true,
                        'prototype' => true,
                    ));
                }
            })
            ->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) {
                /** @var \Eccube\Entity\Shipping $data */
                $data = $event->getData();
                /** @var \Symfony\Component\Form\Form $form */
                $form = $event->getForm();

                if (is_null($data)) {
                    return;
                }

                $Delivery = $data->getDelivery();

                // お届け時間を配送業者で絞り込み
                $form->add('DeliveryTime', EntityType::class, array(
                    'label' => 'お届け時間',
                    'class' => 'Eccube\Entity\DeliveryTime',
                    'choice_label' => 'delivery_time',
                    // FIXME 'empty_valuey' => '指定なし',
                    // 'empty_data' => null,
                    'required' => false,
                    'query_builder' => function (EntityRepository $er) use($Delivery) {
                        return $er->createQueryBuilder('dt')
                            ->where('dt.Delivery = :Delivery')
                            ->setParameter('Delivery', $Delivery);
                    },
                ));

            })
            ->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) use ($app) {
                $data = $event->getData();
                $form = $event->getForm();
                if (!$data) {
                    return;
                }

                $value = $data['Delivery'];
                if (empty($value)) {
                    $value = 0;
                }
                $Delivery = $app['eccube.repository.delivery']->find($value);

                // お届け時間を配送業者で絞り込み
                $form->add('DeliveryTime', EntityType::class, array(
                    'label' => 'お届け時間',
                    'class' => 'Eccube\Entity\DeliveryTime',
                    'choice_label' => 'delivery_time',
                    'placeholder' => '指定なし',
                    // 'empty_data' => null,
                    'required' => false,
                    'query_builder' => function (EntityRepository $er) use($Delivery) {
                        return $er->createQueryBuilder('dt')
                            ->where('dt.Delivery = :Delivery')
                            ->setParameter('Delivery', $Delivery);
                    },
                ));
            })
            ->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) use ($BaseInfo) {
                if ($BaseInfo->getOptionMultipleShipping() == Constant::ENABLED) {
                    $form = $event->getForm();
                    $shipmentItems = $form['ShipmentItems']->getData();

                    if (empty($shipmentItems) || count($shipmentItems) < 1) {
                        // 画面下部にエラーメッセージを表示させる
                        $form['shipping_delivery_date']->addError(new FormError('商品が追加されていません。'));
                    }
                }
            });
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => 'Eccube\Entity\Shipping',
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'shipping';
    }
}
