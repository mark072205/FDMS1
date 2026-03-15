<?php

namespace App\Form;

use App\Entity\Users;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\Regex;

class UsersType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $allowAdminCreation = $options['allow_admin_creation'] ?? false;
        
        // Build choices based on whether admin creation is allowed
        $userTypeChoices = ['Staff' => 'staff'];
        if ($allowAdminCreation) {
            $userTypeChoices = ['Admin' => 'admin', 'Staff' => 'staff'];
        }
        
        $builder
            ->add('username', TextType::class, [
                'label' => 'Username',
                'attr' => [
                    'placeholder' => 'e.g. johndoe',
                    'class' => 'form-control',
                    'autocomplete' => 'username'
                ]
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email Address',
                'attr' => [
                    'placeholder' => 'e.g. john.doe@example.com',
                    'class' => 'form-control',
                    'autocomplete' => 'email'
                ]
            ])
            ->add('firstName', TextType::class, [
                'label' => 'First Name',
                'attr' => [
                    'placeholder' => 'Enter first name',
                    'class' => 'form-control',
                    'autocomplete' => 'given-name'
                ]
            ])
            ->add('lastName', TextType::class, [
                'label' => 'Last Name',
                'required' => false,
                'empty_data' => null,
                'attr' => [
                    'placeholder' => 'Enter last name (optional for Admin/Staff)',
                    'class' => 'form-control',
                    'autocomplete' => 'family-name'
                ]
            ])
            ->add('password', PasswordType::class, [
                'label' => 'Password',
                'attr' => [
                    'placeholder' => 'Enter secure password',
                    'class' => 'form-control',
                    'autocomplete' => 'new-password'
                ],
                // No constraints here - validation is handled at entity level with validation groups
            ])
            ->add('confirmPassword', PasswordType::class, [
                'label' => 'Confirm Password',
                'mapped' => false,
                'required' => true,
                'constraints' => [
                    new NotBlank([
                        'message' => 'Please confirm your password.'
                    ])
                ],
                'attr' => [
                    'placeholder' => 'Repeat password',
                    'class' => 'form-control',
                    'autocomplete' => 'new-password'
                ]
            ])
            ->add('userType', ChoiceType::class, [
                'label' => 'User Type',
                'choices' => $userTypeChoices,
                'data' => 'staff', // Default to staff
                'attr' => [
                    'class' => 'form-control'
                ]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Users::class,
            'allow_admin_creation' => false,
        ]);
    }
}
