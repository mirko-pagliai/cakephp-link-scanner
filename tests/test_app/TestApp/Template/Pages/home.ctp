<?php
use Cake\Routing\Router;
?>
<h1>Home</h1>

<a title="Google" href="http://google.it">Google</a>

<br />

<a href="#text">Text</a>
<a>No href...</a>

<a href="<?= Router::url(['controller' => 'Pages', 'action' => 'display', 'firstpage']) ?>">First page</a>
<a href="<?= Router::url(['controller' => 'Pages', 'action' => 'display', 'secondpage']) ?>">First page</a>