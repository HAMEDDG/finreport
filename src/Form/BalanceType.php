<?php

namespace App\Form;

use App\Entity\Balance;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\NotBlank;

class BalanceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('entreprise', TextType::class, [
                'label' => "Nom de l'entreprise",
                'constraints' => [new NotBlank(message: "Le nom de l'entreprise est obligatoire.")],
            ])
            ->add('exercice', TextType::class, [
                'label' => 'Exercice comptable',
                'attr' => ['placeholder' => '2026'],
                'constraints' => [new NotBlank(message: "L'exercice est obligatoire.")],
            ])
            ->add('dateDebut', DateType::class, [
                'label' => 'Date de début',
                'widget' => 'single_text',
                'constraints' => [new NotBlank(message: 'La date de début est obligatoire.')],
            ])
            ->add('dateFin', DateType::class, [
                'label' => 'Date de fin',
                'widget' => 'single_text',
                'constraints' => [new NotBlank(message: 'La date de fin est obligatoire.')],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
            ])
            ->add('fichier', FileType::class, [
                'label' => 'Fichier de balance',
                'mapped' => false,
                'required' => true,
                'constraints' => [
                    new NotBlank(message: 'Le fichier de balance est obligatoire.'),
                    new File(
                        maxSize: '20M',
                        extensions: [
                            'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
                            'xls' => ['application/vnd.ms-excel', 'application/msexcel', 'application/x-msexcel', 'zz-application/zz-winassoc-xls'],
                            // Un CSV en texte brut est très souvent détecté comme "text/plain" par le
                            // système (aucune signature binaire propre au format CSV) : on l'accepte
                            // en plus des types MIME "csv" standards pour ne pas rejeter à tort de vrais CSV.
                            'csv' => ['text/csv', 'application/csv', 'text/x-comma-separated-values', 'text/x-csv', 'text/plain'],
                        ],
                        extensionsMessage: 'Formats acceptés : XLSX, XLS, CSV uniquement.',
                        maxSizeMessage: 'Le fichier ne doit pas dépasser 20 Mo.',
                    ),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Balance::class]);
    }
}
