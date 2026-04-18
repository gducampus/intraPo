<?php

namespace App\Form;

use App\Entity\DocumentFolder;
use App\Repository\RoleRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Throwable;

class DocumentFolderType extends AbstractType
{
    public function __construct(private readonly RoleRepository $roleRepository)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var DocumentFolder|null $folder */
        $folder = $options['data'] instanceof DocumentFolder ? $options['data'] : null;

        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom du dossier',
            ])
            ->add('description', TextareaType::class, [
                'required' => false,
                'label' => 'Description',
            ])
            ->add('parent', EntityType::class, [
                'class' => DocumentFolder::class,
                'required' => false,
                'choices' => $options['parent_choices'],
                'placeholder' => 'Dossier racine',
                'choice_label' => static fn (DocumentFolder $value): string => str_repeat('- ', $value->getDepth()).($value->getName() ?? 'Dossier'),
                'label' => 'Dossier parent',
            ])
            ->add('position', IntegerType::class, [
                'required' => false,
                'empty_data' => '0',
                'label' => 'Ordre',
            ])
        ;

        if ($options['use_root_access_select']) {
            $builder->add('accessRoles', ChoiceType::class, [
                'required' => false,
                'multiple' => false,
                'expanded' => false,
                'choices' => $this->buildRoleChoices($folder),
                'placeholder' => 'Tous les roles',
                'label' => 'Role autorise',
                'help' => 'Choisissez un role pour limiter l acces a ce dossier racine.',
                'attr' => [
                    'class' => 'form-control text-sm js-select2',
                    'data-placeholder' => 'Choisir un role',
                ],
            ]);

            $builder->get('accessRoles')->addModelTransformer(new CallbackTransformer(
                function (?array $roles): string {
                    $roles = array_map('strtoupper', $roles ?? []);
                    $roles = array_filter($roles, static fn (string $role): bool => $role !== '');
                    $roles = array_values(array_unique($roles));

                    return $roles[0] ?? '';
                },
                function ($role): array {
                    if (is_array($role)) {
                        $role = $role[0] ?? '';
                    }

                    $normalized = strtoupper(trim((string) $role));

                    return $normalized === '' ? [] : [$normalized];
                }
            ));

            return;
        }

        $builder
            ->add('isSecured', CheckboxType::class, [
                'required' => false,
                'label' => 'Dossier securise',
            ])
            ->add('accessRoles', ChoiceType::class, [
                'required' => false,
                'multiple' => true,
                'expanded' => false,
                'choices' => $this->buildRoleChoices($folder),
                'label' => 'Roles autorises',
                'help' => 'Roles autorises lorsque le dossier est securise.',
                'attr' => [
                    'class' => 'form-control text-sm js-select2',
                    'data-placeholder' => 'Selectionner un ou plusieurs roles',
                ],
            ])
        ;

        $builder->get('accessRoles')->addModelTransformer(new CallbackTransformer(
            function (?array $roles): array {
                $roles = array_map('strtoupper', $roles ?? []);
                $roles = array_filter($roles, static fn (string $role): bool => $role !== '');

                return array_values(array_unique($roles));
            },
            function (?array $roles): array {
                $roles = array_map(
                    static fn (string $role): string => strtoupper(trim($role)),
                    $roles ?? []
                );
                $roles = array_filter($roles, static fn (string $role): bool => $role !== '');

                return array_values(array_unique($roles));
            }
        ));
    }

    /**
     * @return array<string, string>
     */
    private function buildRoleChoices(?DocumentFolder $folder): array
    {
        $choices = [];
        $usedLabels = [];

        try {
            $catalogRoles = $this->roleRepository->findAllOrderedByLabel();
        } catch (Throwable) {
            $catalogRoles = [];
        }

        foreach ($catalogRoles as $role) {
            $code = strtoupper(trim((string) $role->getCode()));
            if ($code === '') {
                continue;
            }

            $label = trim((string) $role->getLabel());
            $display = $label !== '' ? $label : $this->humanizeRoleCode($code);
            $display = $this->deduplicateLabel($display, $usedLabels);
            $choices[$display] = $code;
        }

        if ($folder instanceof DocumentFolder) {
            foreach ($folder->getAccessRoles() as $roleCode) {
                $roleCode = strtoupper(trim($roleCode));
                if ($roleCode === '') {
                    continue;
                }

                if (!in_array($roleCode, $choices, true)) {
                    $display = $this->deduplicateLabel($this->humanizeRoleCode($roleCode), $usedLabels);
                    $choices[$display] = $roleCode;
                }
            }
        }

        uksort($choices, static fn (string $a, string $b): int => strnatcasecmp($a, $b));

        return $choices;
    }

    /**
     * @param array<string, bool> $usedLabels
     */
    private function deduplicateLabel(string $label, array &$usedLabels): string
    {
        $base = trim($label) !== '' ? trim($label) : 'Role';
        $candidate = $base;
        $index = 2;

        while (isset($usedLabels[$candidate])) {
            $candidate = sprintf('%s %d', $base, $index);
            ++$index;
        }

        $usedLabels[$candidate] = true;

        return $candidate;
    }

    private function humanizeRoleCode(string $roleCode): string
    {
        $normalized = strtoupper(trim($roleCode));
        $normalized = preg_replace('/^ROLE_/', '', $normalized) ?? $normalized;
        $normalized = str_replace('_', ' ', $normalized);
        $normalized = strtolower($normalized);

        return ucfirst($normalized);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => DocumentFolder::class,
            'parent_choices' => [],
            'use_root_access_select' => false,
        ]);
    }
}
