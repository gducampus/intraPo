<?php

namespace App\Form;

use App\Entity\DocumentItem;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class DocumentItemType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Titre',
            ])
            ->add('description', TextareaType::class, [
                'required' => false,
                'label' => 'Description',
            ])
            ->add('itemType', ChoiceType::class, [
                'choices' => [
                    'Lien video (YouTube ou Vimeo)' => DocumentItem::TYPE_VIDEO_LINK,
                    'Lien externe' => DocumentItem::TYPE_EXTERNAL_LINK,
                    'Fichier (PDF, image, autre)' => DocumentItem::TYPE_UPLOADED_FILE,
                ],
                'label' => 'Type de fichier',
            ])
            ->add('externalUrl', TextType::class, [
                'required' => false,
                'label' => 'URL',
            ])
            ->add('uploadedFile', FileType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Fichier a importer',
                'constraints' => [
                    new File([
                        'maxSize' => '50M',
                    ]),
                ],
            ])
            ->add('position', IntegerType::class, [
                'required' => false,
                'empty_data' => '0',
                'label' => 'Ordre',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => DocumentItem::class,
        ]);
    }
}
