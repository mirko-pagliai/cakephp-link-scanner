<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <title><?= $this->fetch('title'); ?></title>
    <?= $this->Html->meta('icon') ?>

    <?= $this->Html->css('default') ?>
    <?= $this->Html->script('default') ?>

    <?= $this->fetch('script') ?>

</head>
<body>
    <?= $this->Flash->render(); ?>
    <?= $this->fetch('content'); ?>

</body>
</html>