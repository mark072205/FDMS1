<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

class StaffSettingsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $user = $options['user'] ?? null;
        $includeRole = $options['include_role'] ?? false;
        $includeUsername = $options['include_username'] ?? true;
        $isStaff = $user && ($user->getUserType() === 'staff' || $user->getRole() === 'staff');
        $isAdmin = $user && ($user->getUserType() === 'admin' || $user->getRole() === 'admin');
        $isAdminOrStaff = $isAdmin || $isStaff;
        
        // Profile Information
        $builder
            ->add('firstName', TextType::class, [
                'label' => 'First Name',
                'data' => $user?->getFirstName(),
                'required' => true,
                'attr' => [
                    'placeholder' => 'Enter your first name',
                    'class' => 'form-control'
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'First name is required.',
                    ]),
                    new Length([
                        'min' => 2,
                        'max' => 100,
                        'minMessage' => 'First name must be at least {{ limit }} characters long.',
                        'maxMessage' => 'First name cannot be longer than {{ limit }} characters.',
                    ]),
                ],
            ])
            ->add('lastName', TextType::class, [
                'label' => 'Last Name',
                'data' => $user?->getLastName(),
                'required' => $isAdminOrStaff ? false : true,
                'attr' => [
                    'placeholder' => $isAdminOrStaff ? 'Enter your last name (optional)' : 'Enter your last name',
                    'class' => 'form-control'
                ],
                'constraints' => [
                    new Length([
                        'min' => 2,
                        'max' => 100,
                        'minMessage' => 'Last name must be at least {{ limit }} characters long.',
                        'maxMessage' => 'Last name cannot be longer than {{ limit }} characters.',
                    ]),
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email Address',
                'data' => $user?->getEmail(),
                'required' => true,
                'attr' => [
                    'placeholder' => 'your.email@example.com',
                    'class' => 'form-control'
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Email is required.',
                    ]),
                    new Email([
                        'message' => 'The email "{{ value }}" is not a valid email address.',
                    ]),
                    new Regex([
                        'pattern' => '/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/',
                        'message' => 'Please enter a valid email address with @ and domain (e.g., user@example.com).',
                    ]),
                ],
            ]);

        // Add username field if needed (for staff settings, not for admin editing)
        if ($includeUsername) {
            $builder->add('username', TextType::class, [
                'label' => 'Username',
                'data' => $user?->getUsername(),
                'required' => true,
                'attr' => [
                    'placeholder' => 'Enter your username',
                    'class' => 'form-control'
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Username is required.',
                    ]),
                    new Length([
                        'min' => 3,
                        'max' => 100,
                        'minMessage' => 'Username must be at least {{ limit }} characters long.',
                        'maxMessage' => 'Username cannot be longer than {{ limit }} characters.',
                    ]),
                    new Regex([
                        'pattern' => '/^[a-zA-Z0-9_]+$/',
                        'message' => 'Username can only contain letters, numbers, and underscores.',
                    ]),
                ],
            ]);
        }

        // Add role field if needed (for admin editing users)
        if ($includeRole) {
            $builder->add('role', ChoiceType::class, [
                'label' => 'Role',
                'choices' => [
                    'Staff' => 'staff',
                    'Admin' => 'admin',
                ],
                'data' => $user?->getRole(),
                'attr' => [
                    'class' => 'form-control'
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Role is required.',
                    ]),
                ],
            ]);
        }

        // Add password field if needed (for admin editing users)
        $includePassword = $options['include_password'] ?? false;
        if ($includePassword) {
            // Check if the user being edited is admin or staff
            $targetUserIsAdmin = $user && ($user->getUserType() === 'admin' || $user->getRole() === 'admin');
            $targetUserIsStaff = $user && ($user->getUserType() === 'staff' || $user->getRole() === 'staff');
            $targetUserIsAdminOrStaff = $targetUserIsAdmin || $targetUserIsStaff;
            
            // Build constraints - no restrictions for admin/staff
            $passwordConstraints = [];
            if (!$targetUserIsAdminOrStaff) {
                $passwordConstraints[] = new Length([
                    'min' => 8,
                    'max' => 4096,
                    'minMessage' => 'Password must be at least {{ limit }} characters long.',
                ]);
            }
            
            $builder->add('password', PasswordType::class, [
                'label' => 'New Password',
                'required' => false,
                'attr' => [
                    'placeholder' => $targetUserIsAdminOrStaff ? 'Leave blank to keep current password (no restrictions)' : 'Leave blank to keep current password',
                    'class' => 'form-control',
                    'autocomplete' => 'new-password'
                ],
                'constraints' => $passwordConstraints,
            ]);
        }

        // Add userType field if needed (for admin promoting any user to admin)
        $includeUserType = $options['include_user_type'] ?? false;
        if ($includeUserType) {
            // Build choices based on current user type
            $currentUserType = $user?->getUserType() ?? $user?->getRole();
            $choices = [];
            
            // Add current user type as an option
            if ($currentUserType === 'client') {
                $choices['Client'] = 'client';
            } elseif ($currentUserType === 'designer') {
                $choices['Designer'] = 'designer';
            } elseif ($currentUserType === 'staff') {
                $choices['Staff'] = 'staff';
            }
            
            // Always add Admin as an option for promotion
            $choices['Admin'] = 'admin';
            
            $builder->add('userType', ChoiceType::class, [
                'label' => 'User Type',
                'choices' => $choices,
                'data' => $currentUserType,
                'attr' => [
                    'class' => 'form-control'
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'User type is required.',
                    ]),
                ],
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'user' => null,
            'data_class' => null,
            'include_role' => false,
            'include_username' => true,
            'include_user_type' => false,
            'include_password' => false,
        ]);
    }
}

