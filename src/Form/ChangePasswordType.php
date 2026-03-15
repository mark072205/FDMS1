<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

class ChangePasswordType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $user = $options['user'] ?? null;
        $isAdmin = $user && ($user->getUserType() === 'admin' || $user->getRole() === 'admin');
        $isStaff = $user && ($user->getUserType() === 'staff' || $user->getRole() === 'staff');
        $isAdminOrStaff = $isAdmin || $isStaff;
        
        // Build constraints for newPassword based on user role
        $newPasswordConstraints = [
            new NotBlank([
                'message' => 'Please enter a new password.',
            ]),
        ];
        
        // Only apply length and regex restrictions if NOT admin or staff
        if (!$isAdminOrStaff) {
            $newPasswordConstraints[] = new Length([
                'min' => 8,
                'minMessage' => 'Your password should be at least {{ limit }} characters.',
                'max' => 4096,
            ]);
            $newPasswordConstraints[] = new Regex([
                'pattern' => '/^(?=.*[a-zA-Z0-9@$!%*?&_])[A-Za-z\d@$!%*?&_]{8,}$/',
                'message' => 'Password must be at least 8 characters and contain at least one of the following: uppercase letter, lowercase letter, number, or special character (@$!%*?&_).',
            ]);
        }
        
        $builder
            ->add('currentPassword', PasswordType::class, [
                'label' => 'Current Password',
                'required' => true,
                'attr' => [
                    'placeholder' => 'Enter your current password',
                    'class' => 'form-control',
                    'autocomplete' => 'current-password'
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Please enter your current password.',
                    ]),
                ],
            ])
            ->add('newPassword', PasswordType::class, [
                'label' => 'New Password',
                'required' => true,
                'attr' => [
                    'placeholder' => $isAdminOrStaff ? 'Enter your new password (no restrictions)' : 'Enter your new password',
                    'class' => 'form-control',
                    'autocomplete' => 'new-password'
                ],
                'constraints' => $newPasswordConstraints,
            ])
            ->add('confirmPassword', PasswordType::class, [
                'label' => 'Confirm New Password',
                'required' => true,
                'attr' => [
                    'placeholder' => 'Confirm your new password',
                    'class' => 'form-control',
                    'autocomplete' => 'new-password'
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Please confirm your new password.',
                    ]),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'user' => null,
        ]);
    }
}

