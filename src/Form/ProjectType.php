<?php

namespace App\Form;

use App\Entity\Project;
use App\Entity\Category;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class ProjectType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Project Title',
                'required' => true,
                'attr' => [
                    'placeholder' => 'e.g., Logo Design for Tech Startup',
                    'class' => 'form-control',
                    'maxlength' => 200
                ],
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => 'Project title is required.'
                    ]),
                    new Assert\Length([
                        'min' => 3,
                        'max' => 200,
                        'minMessage' => 'Project title must be at least {{ limit }} characters long.',
                        'maxMessage' => 'Project title cannot be longer than {{ limit }} characters.'
                    ])
                ]
            ])
            ->add('category', EntityType::class, [
                'label' => 'Category',
                'class' => Category::class,
                'choice_label' => 'name',
                'placeholder' => 'Select a category',
                'required' => true,
                'attr' => [
                    'class' => 'form-control'
                ],
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => 'Please select a category.'
                    ])
                ]
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => true,
                'attr' => [
                    'placeholder' => 'Describe your project requirements, style preferences, timeline, and any specific details...',
                    'class' => 'form-control',
                    'rows' => 6
                ],
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => 'Project description is required.'
                    ]),
                    new Assert\Length([
                        'min' => 10,
                        'minMessage' => 'Project description must be at least {{ limit }} characters long.'
                    ])
                ]
            ])
            ->add('budget', NumberType::class, [
                'label' => 'Budget',
                'required' => true,
                'scale' => 2,
                'attr' => [
                    'placeholder' => '0.00',
                    'class' => 'form-control',
                    'step' => '0.01',
                    'min' => '0'
                ],
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => 'Budget is required.'
                    ]),
                    new Assert\Type([
                        'type' => 'numeric',
                        'message' => 'Budget must be a valid number.'
                    ]),
                    new Assert\Positive([
                        'message' => 'Budget must be a positive number.'
                    ])
                ]
            ])
            ->add('status', HiddenType::class, [
                'data' => 'pending',
                'required' => true
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Project::class,
        ]);
    }
}

