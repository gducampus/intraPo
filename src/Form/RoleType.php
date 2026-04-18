<?php

namespace App\Form;

use App\Entity\Role;
use App\Service\RoleCodeNormalizer;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RoleType extends AbstractType
{
    public function __construct(private readonly RoleCodeNormalizer $roleCodeNormalizer)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('label', TextType::class, [
                'label' => 'Nom',
            ])
            ->add('code', TextType::class, [
                'label' => 'Code role',
                'help' => 'Format normalise automatiquement en ROLE_XXX.',
                'attr' => [
                    'placeholder' => 'ROLE_MODULE_RH',
                    'autocomplete' => 'off',
                ],
            ])
            ->add('description', TextareaType::class, [
                'required' => false,
                'attr' => [
                    'rows' => 3,
                ],
            ]);

        $builder->get('code')->addModelTransformer(new CallbackTransformer(
            fn (?string $code): string => $code ?? '',
            fn (?string $code): string => $this->roleCodeNormalizer->normalize((string) $code)
        ));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Role::class,
        ]);
    }
}

