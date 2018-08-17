<h1>Home</h1>

<a title="Google" href="http://google.it">Google</a>

<br />

<a href="#text">Text</a>
<a>No href...</a>

<?php
    echo $this->Html->link('First page', ['controller' => 'Pages', 'action' => 'display', 'firstpage']);
    echo $this->Html->link('Second page', ['controller' => 'Pages', 'action' => 'display', 'secondpage']);
?>
