<?php

declare(strict_types=1);

require __DIR__ . '/vendor/jield/framework/autoload.php';

if (\Composer\InstalledVersions::getPrettyVersion('jield/framework') !== $argv[1]) {
    throw new RuntimeException('Composer installed an unexpected framework version.');
}

if (
    !is_a(\Laminas\Stdlib\SplPriorityQueue::class, \Jield\Stdlib\SplPriorityQueue::class, true)
    || !is_a(\Laminas\Router\RouteMatch::class, \Jield\Mvc\Router\RouteMatch::class, true)
) {
    throw new RuntimeException('The application bootstrap did not register the compatibility adapters.');
}

$form = new \Jield\Form\Form('consumer');
$form->add(['name' => 'title', 'type' => \Jield\Form\Element\Text::class]);
$form->setData(['title' => 'Composer alpha']);
if (!$form->isValid() || $form->getData()['title'] !== 'Composer alpha') {
    throw new RuntimeException('The installed form integration failed.');
}

$pdf = new \Jield\Pdf\Pdf();
$pdf->AddPage();
$pdf->Write(0, 'Composer alpha');
if (!str_starts_with($pdf->Output('consumer.pdf', 'S'), '%PDF-')) {
    throw new RuntimeException('The installed PDF integration failed.');
}
