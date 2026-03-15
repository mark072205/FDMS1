<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SettingsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $settings = $options['settings'] ?? [];

        // General Settings
        $builder
            ->add('site_name', TextType::class, [
                'label' => 'Site Name',
                'data' => $settings['site_name'] ?? '',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Enter site name',
                    'class' => 'form-control'
                ]
            ])
            ->add('site_logo', UrlType::class, [
                'label' => 'Site Logo URL',
                'data' => $settings['site_logo'] ?? '',
                'required' => false,
                'attr' => [
                    'placeholder' => 'https://example.com/logo.png',
                    'class' => 'form-control'
                ]
            ])
            ->add('contact_email', EmailType::class, [
                'label' => 'Contact Email',
                'data' => $settings['contact_email'] ?? '',
                'required' => false,
                'attr' => [
                    'placeholder' => 'contact@example.com',
                    'class' => 'form-control'
                ]
            ])
            ->add('contact_phone', TextType::class, [
                'label' => 'Contact Phone',
                'data' => $settings['contact_phone'] ?? '',
                'required' => false,
                'attr' => [
                    'placeholder' => '+1 (555) 123-4567',
                    'class' => 'form-control'
                ]
            ])
            ->add('site_description', TextareaType::class, [
                'label' => 'Site Description',
                'data' => $settings['site_description'] ?? '',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Enter site description',
                    'class' => 'form-control',
                    'rows' => 3
                ]
            ])
        ;

        // Email Settings
        $builder
            ->add('smtp_host', TextType::class, [
                'label' => 'SMTP Host',
                'data' => $settings['smtp_host'] ?? '',
                'required' => false,
                'attr' => [
                    'placeholder' => 'smtp.example.com',
                    'class' => 'form-control'
                ]
            ])
            ->add('smtp_port', IntegerType::class, [
                'label' => 'SMTP Port',
                'data' => $settings['smtp_port'] ?? 587,
                'required' => false,
                'attr' => [
                    'placeholder' => '587',
                    'class' => 'form-control'
                ]
            ])
            ->add('smtp_username', TextType::class, [
                'label' => 'SMTP Username',
                'data' => $settings['smtp_username'] ?? '',
                'required' => false,
                'attr' => [
                    'placeholder' => 'smtp@example.com',
                    'class' => 'form-control'
                ]
            ])
            ->add('smtp_password', TextType::class, [
                'label' => 'SMTP Password',
                'data' => $settings['smtp_password'] ?? '',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Enter SMTP password',
                    'class' => 'form-control'
                ]
            ])
            ->add('smtp_encryption', ChoiceType::class, [
                'label' => 'SMTP Encryption',
                'choices' => [
                    'None' => 'none',
                    'TLS' => 'tls',
                    'SSL' => 'ssl'
                ],
                'data' => $settings['smtp_encryption'] ?? 'tls',
                'required' => false,
                'attr' => [
                    'class' => 'form-control'
                ]
            ])
            ->add('email_from_address', EmailType::class, [
                'label' => 'From Email Address',
                'data' => $settings['email_from_address'] ?? '',
                'required' => false,
                'attr' => [
                    'placeholder' => 'noreply@example.com',
                    'class' => 'form-control'
                ]
            ])
            ->add('email_from_name', TextType::class, [
                'label' => 'From Name',
                'data' => $settings['email_from_name'] ?? '',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Your Site Name',
                    'class' => 'form-control'
                ]
            ])
        ;

        // Payment Settings
        $builder
            ->add('payment_gateway', ChoiceType::class, [
                'label' => 'Payment Gateway',
                'choices' => [
                    'Stripe' => 'stripe',
                    'PayPal' => 'paypal',
                    'Manual' => 'manual'
                ],
                'data' => $settings['payment_gateway'] ?? 'manual',
                'required' => false,
                'attr' => [
                    'class' => 'form-control'
                ]
            ])
            ->add('stripe_public_key', TextType::class, [
                'label' => 'Stripe Public Key',
                'data' => $settings['stripe_public_key'] ?? '',
                'required' => false,
                'attr' => [
                    'placeholder' => 'pk_test_...',
                    'class' => 'form-control'
                ]
            ])
            ->add('stripe_secret_key', TextType::class, [
                'label' => 'Stripe Secret Key',
                'data' => $settings['stripe_secret_key'] ?? '',
                'required' => false,
                'attr' => [
                    'placeholder' => 'sk_test_...',
                    'class' => 'form-control'
                ]
            ])
            ->add('paypal_client_id', TextType::class, [
                'label' => 'PayPal Client ID',
                'data' => $settings['paypal_client_id'] ?? '',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Enter PayPal Client ID',
                    'class' => 'form-control'
                ]
            ])
            ->add('platform_fee_percentage', NumberType::class, [
                'label' => 'Platform Fee (%)',
                'data' => $settings['platform_fee_percentage'] ?? 0,
                'required' => false,
                'scale' => 2,
                'attr' => [
                    'placeholder' => '5.00',
                    'class' => 'form-control',
                    'step' => '0.01',
                    'min' => '0',
                    'max' => '100'
                ]
            ])
        ;

        // Security Settings
        $builder
            ->add('password_min_length', IntegerType::class, [
                'label' => 'Minimum Password Length',
                'data' => $settings['password_min_length'] ?? 8,
                'required' => false,
                'attr' => [
                    'placeholder' => '8',
                    'class' => 'form-control',
                    'min' => '6',
                    'max' => '32'
                ]
            ])
            ->add('password_require_uppercase', CheckboxType::class, [
                'label' => 'Require Uppercase Letter',
                'data' => isset($settings['password_require_uppercase']) ? (bool)$settings['password_require_uppercase'] : true,
                'required' => false,
                'attr' => [
                    'class' => 'form-check-input'
                ]
            ])
            ->add('password_require_lowercase', CheckboxType::class, [
                'label' => 'Require Lowercase Letter',
                'data' => isset($settings['password_require_lowercase']) ? (bool)$settings['password_require_lowercase'] : true,
                'required' => false,
                'attr' => [
                    'class' => 'form-check-input'
                ]
            ])
            ->add('password_require_number', CheckboxType::class, [
                'label' => 'Require Number',
                'data' => isset($settings['password_require_number']) ? (bool)$settings['password_require_number'] : true,
                'required' => false,
                'attr' => [
                    'class' => 'form-check-input'
                ]
            ])
            ->add('password_require_special', CheckboxType::class, [
                'label' => 'Require Special Character',
                'data' => isset($settings['password_require_special']) ? (bool)$settings['password_require_special'] : true,
                'required' => false,
                'attr' => [
                    'class' => 'form-check-input'
                ]
            ])
            ->add('session_timeout', IntegerType::class, [
                'label' => 'Session Timeout (minutes)',
                'data' => $settings['session_timeout'] ?? 60,
                'required' => false,
                'attr' => [
                    'placeholder' => '60',
                    'class' => 'form-control',
                    'min' => '5',
                    'max' => '1440'
                ]
            ])
        ;

        // Feature Toggles
        $builder
            ->add('feature_user_registration', CheckboxType::class, [
                'label' => 'Enable User Registration',
                'data' => isset($settings['feature_user_registration']) ? (bool)$settings['feature_user_registration'] : true,
                'required' => false,
                'attr' => [
                    'class' => 'form-check-input'
                ]
            ])
            ->add('feature_email_notifications', CheckboxType::class, [
                'label' => 'Enable Email Notifications',
                'data' => isset($settings['feature_email_notifications']) ? (bool)$settings['feature_email_notifications'] : true,
                'required' => false,
                'attr' => [
                    'class' => 'form-check-input'
                ]
            ])
            ->add('feature_proposals', CheckboxType::class, [
                'label' => 'Enable Proposals',
                'data' => isset($settings['feature_proposals']) ? (bool)$settings['feature_proposals'] : true,
                'required' => false,
                'attr' => [
                    'class' => 'form-check-input'
                ]
            ])
            ->add('feature_file_uploads', CheckboxType::class, [
                'label' => 'Enable File Uploads',
                'data' => isset($settings['feature_file_uploads']) ? (bool)$settings['feature_file_uploads'] : true,
                'required' => false,
                'attr' => [
                    'class' => 'form-check-input'
                ]
            ])
        ;

        // Maintenance Mode
        $builder
            ->add('maintenance_mode', CheckboxType::class, [
                'label' => 'Enable Maintenance Mode',
                'data' => isset($settings['maintenance_mode']) ? (bool)$settings['maintenance_mode'] : false,
                'required' => false,
                'attr' => [
                    'class' => 'form-check-input'
                ]
            ])
            ->add('maintenance_message', TextareaType::class, [
                'label' => 'Maintenance Message',
                'data' => $settings['maintenance_message'] ?? 'The site is currently under maintenance. Please check back later.',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Enter maintenance message',
                    'class' => 'form-control',
                    'rows' => 3
                ]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'settings' => [],
            'csrf_protection' => true,
        ]);
    }
}

