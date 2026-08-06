<?php

namespace App\Form;

use App\Entity\AppSettings;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\NotBlank;

class AppSettingsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nomEntreprise', TextType::class, [
                'label' => "Nom de l'entreprise par défaut",
                'required' => false,
                'attr' => ['placeholder' => 'Utilisé pour préremplir le formulaire d\'import de balance'],
            ])
            ->add('logo', FileType::class, [
                'label' => "Logo de l'entreprise",
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new File(
                        maxSize: '2M',
                        extensions: [
                            'png' => ['image/png'],
                            'jpg' => ['image/jpeg'],
                            'jpeg' => ['image/jpeg'],
                            'svg' => ['image/svg+xml'],
                        ],
                        extensionsMessage: 'Formats acceptés : PNG, JPG, SVG uniquement.',
                        maxSizeMessage: 'Le logo ne doit pas dépasser 2 Mo.',
                    ),
                ],
            ])
            ->add('devise', ChoiceType::class, [
                'label' => 'Devise des rapports',
                'choices' => [
                    'Franc CFA (FCFA)' => 'FCFA',
                    'Euro (EUR)' => 'EUR',
                    'Dollar américain (USD)' => 'USD',
                    'Dollar canadien (CAD)' => 'CAD',
                ],
                'constraints' => [new NotBlank(message: 'La devise est obligatoire.')],
            ])
            ->add('toleranceEquilibre', NumberType::class, [
                'label' => "Tolérance d'équilibre de la balance",
                'help' => "Écart maximal accepté (en unités monétaires) entre le total débit et le total crédit avant de refuser la génération d'un rapport.",
                'scale' => 2,
                'constraints' => [
                    new NotBlank(message: 'La tolérance est obligatoire.'),
                    new GreaterThanOrEqual(value: 0, message: 'La tolérance doit être positive ou nulle.'),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => AppSettings::class]);
    }
}
