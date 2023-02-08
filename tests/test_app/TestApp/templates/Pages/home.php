<h1>Home</h1>

<a title="Google" href="https://google.it">Google</a>

<br />

<!-- These will be ignored -->
<a href="#text">Text</a><br />
<a>No href...</a><br />
<a href="mailto:mymail@example.com">My mail</a><br />
<a href="javascript:alert('hello!');">Javascript alert</a><br />

<?= $this->Html->link('First page', ['controller' => 'Pages', 'action' => 'display', 'first_page']) ?>
<?= $this->Html->link('Second page', ['controller' => 'Pages', 'action' => 'display', 'second_page']) ?>
<?= $this->Html->link('No html', ['controller' => 'Pages', 'action' => 'display', 'no-html']) ?>
<?= $this->Html->link('Redirect to home page', '/pages/redirect') ?>
<?= $this->Html->link('Again, redirect to home page', '/pages/same-redirect') ?>
