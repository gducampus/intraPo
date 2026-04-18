<?php

namespace App\Form;

use App\Entity\PoUser;
use App\Repository\RoleRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Throwable;

class PoUserType extends AbstractType
{
    public function __construct(private readonly RoleRepository $roleRepository)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $user = $options['data'] instanceof PoUser ? $options['data'] : null;

        $builder
            ->add('name', TextType::class, [
                'required' => false,
            ])
            ->add('email', EmailType::class)
            ->add('roles', ChoiceType::class, [
                'required' => false,
                'multiple' => true,
                'expanded' => false,
                'choices' => $this->buildRoleChoices($user),
                'help' => 'Selection multiple de roles. Le role utilisateur de base est applique automatiquement.',
                'attr' => [
                    'class' => 'form-control text-sm js-select2',
                    'data-placeholder' => 'Selectionner un ou plusieurs roles',
                ],
            ]);

        $builder->get('roles')->addModelTransformer(new CallbackTransformer(
            function (?array $roles): array {
                $roles = array_map('strtoupper', $roles ?? []);
                $roles = array_filter($roles, static fn (string $role): bool => $role !== 'ROLE_USER');
                $roles = array_values(array_unique($roles));

                return $roles;
            },
            function (?array $roles): array {
                if (!$roles) {
                    return [];
                }

                $roles = array_map(static fn (string $role): string => strtoupper(trim($role)), $roles);
                $roles = array_filter($roles, static fn (string $role): bool => $role !== '');
                $roles = array_values(array_unique($roles));
                $roles = array_values(array_filter($roles, static fn (string $role): bool => $role !== 'ROLE_USER'));

                return $roles;
            }
        ));
    }

    /**
     * @return array<string, string>
     */
    private function buildRoleChoices(?PoUser $user): array
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
            if ($code === '' || $code === 'ROLE_USER') {
                continue;
            }

            $label = trim((string) $role->getLabel());
            $display = $label !== '' ? $label : $this->humanizeRoleCode($code);
            $display = $this->deduplicateLabel($display, $usedLabels);
            $choices[$display] = $code;
        }

        if ($user instanceof PoUser) {
            foreach ($user->getRoles() as $roleCode) {
                $roleCode = strtoupper(trim((string) $roleCode));
                if ($roleCode === '' || $roleCode === 'ROLE_USER') {
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
            'data_class' => PoUser::class,
        ]);
    }
}
