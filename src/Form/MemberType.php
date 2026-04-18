<?php

namespace App\Form;

use App\Entity\Member;
use App\Entity\Secteur;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MemberType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('rgpdConsent', CheckboxType::class, [
                'required' => false,
            ])
            ->add('shortTitle', TextType::class, [
                'required' => false,
            ])
            ->add('lastNameOrCompany', TextType::class, [
                'required' => false,
            ])
            ->add('birthName', TextType::class, [
                'required' => false,
            ])
            ->add('firstNameOrService', TextType::class, [
                'required' => false,
            ])
            ->add('address', TextareaType::class, [
                'required' => false,
            ])
            ->add('postalCode', TextType::class, [
                'required' => false,
            ])
            ->add('city', TextType::class, [
                'required' => false,
            ])
            ->add('homePhone', TextType::class, [
                'required' => false,
            ])
            ->add('mobilePhone', TextType::class, [
                'required' => false,
            ])
            ->add('preferredEmail', TextType::class, [
                'required' => false,
            ])
            ->add('birthOrFoundedAt', DateType::class, [
                'required' => false,
                'widget' => 'single_text',
            ])
            ->add('baptismAt', DateType::class, [
                'required' => false,
                'widget' => 'single_text',
            ])
            ->add('lastContactName', TextType::class, [
                'required' => false,
            ])
            ->add('contactChannel', TextType::class, [
                'required' => false,
            ])
            ->add('lastContactAt', DateType::class, [
                'required' => false,
                'widget' => 'single_text',
            ])
            ->add('latitude', NumberType::class, [
                'required' => false,
                'scale' => 7,
            ])
            ->add('longitude', NumberType::class, [
                'required' => false,
                'scale' => 7,
            ])
            ->add('modificationToApply', TextareaType::class, [
                'required' => false,
            ])
            ->add('remarks', TextareaType::class, [
                'required' => false,
            ])
            ->add('sector', EntityType::class, [
                'class' => Secteur::class,
                'choice_label' => 'name',
                'required' => false,
                'placeholder' => 'Aucun secteur',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Member::class,
        ]);
    }
}
