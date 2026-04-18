<?php

namespace App\Form;

use App\Entity\Module;
use App\Repository\RoleRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Throwable;

class ModuleType extends AbstractType
{
    public function __construct(private readonly RoleRepository $roleRepository)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $module = $options['data'] instanceof Module ? $options['data'] : null;

        $builder
            ->add('title', TextType::class)
            ->add('tag', TextType::class)
            ->add('description', TextareaType::class, [
                'required' => false,
            ])
            ->add('isAvailable', CheckboxType::class, [
                'required' => false,
            ])
            ->add('accessRoles', ChoiceType::class, [
                'required' => false,
                'multiple' => true,
                'expanded' => false,
                'choices' => $this->buildRoleChoices($module),
                'help' => "Ces roles donnent acces au module en plus des roles calcules depuis le tag.",
                'attr' => [
                    'class' => 'form-control text-sm js-select2',
                    'data-placeholder' => 'Ajouter des roles autorises',
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
    private function buildRoleChoices(?Module $module): array
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

        if ($module instanceof Module) {
            foreach ($module->getAccessRoles() as $roleCode) {
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
            'data_class' => Module::class,
        ]);
    }
}
