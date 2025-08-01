<?php
/**
 * Globale Sprachschalter-Komponente
 * 
 * Verwendung:
 * <?php include __DIR__ . '/components/language-switcher.php'; ?>
 * 
 * Optionen via $languageSwitcherOptions:
 * - style: 'dropdown' (default), 'buttons', 'flags'
 * - position: 'header', 'footer', 'inline'
 * - showNames: true/false
 * - showFlags: true/false
 * 
 * @author 2Brands Media GmbH
 */

// Standard-Optionen
$options = array_merge([
    'style' => 'dropdown',
    'position' => 'header',
    'showNames' => true,
    'showFlags' => false,
    'class' => ''
], $languageSwitcherOptions ?? []);

// UIAgent holen
$ui = $container->get('ui');
$i18n = $container->get('i18n');

// Position-spezifische Klassen
switch ($options['position']) {
    case 'header':
        $options['class'] .= ' relative';
        break;
    case 'footer':
        $options['class'] .= ' inline-block';
        break;
    case 'inline':
        $options['class'] .= ' inline-flex items-center';
        break;
}

// Sprachschalter rendern
echo $ui->renderLanguageSwitcher($options);
?>