<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class UserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => 'Adresse e-mail',
                'constraints' => [new NotBlank(message: "L'adresse e-mail est obligatoire.")],
            ])
            ->add('roleChoisi', ChoiceType::class, [
                'label' => 'Rôle',
                'mapped' => false,
                'choices' => [
                    'Administrateur' => 'ROLE_ADMIN',
                    'Utilisateur' => 'ROLE_USER',
                ],
                'data' => in_array('ROLE_ADMIN', $options['data']->getRoles(), true) ? 'ROLE_ADMIN' : 'ROLE_USER',
            ])
            ->add('plainPassword', PasswordType::class, [
                'label' => $options['is_edit'] ? 'Nouveau mot de passe (laisser vide pour ne pas changer)' : 'Mot de passe',
                'mapped' => false,
                'required' => !$options['is_edit'],
                'constraints' => $options['is_edit'] ? [] : [
                    new NotBlank(message: 'Le mot de passe est obligatoire.'),
                    new Length(min: 6, minMessage: 'Le mot de passe doit contenir au moins {{ limit }} caractères.'),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'is_edit' => false,
        ]);
    }
}
