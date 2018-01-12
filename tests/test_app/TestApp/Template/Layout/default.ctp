<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <title><?= $this->fetch('title'); ?></title>
    <?= $this->Html->meta('icon') ?>

    <?= $this->Html->css('cake.generic') ?>

    <?= $this->fetch('script') ?>

</head>
<body>
    <?= $this->fetch('content'); ?>

</body>
</html>