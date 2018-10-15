<h1>Home</h1>

<a title="Google" href="http://google.it">Google</a>

<br />

<a href="#text">Text</a>
<a>No href...</a>

<?= $this->Html->link('First page', ['controller' => 'Pages', 'action' => 'display', 'first_page']) ?>
<?= $this->Html->link('Second page', ['controller' => 'Pages', 'action' => 'display', 'second_page']) ?>
<?= $this->Html->link('Redirect to home page', '/pages/redirect') ?>
