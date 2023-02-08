<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Core\Configure;
use Cake\Http\Exception\ForbiddenException;
use Cake\Http\Exception\NotFoundException;
use Cake\Http\Response;
use Cake\View\Exception\MissingTemplateException;

class PagesController extends AppController
{
    /**
     * Displays a view
     *
     * @param array ...$path Path segments
     * @return \Cake\Http\Response|null
     * @throws \Cake\Http\Exception\ForbiddenException When a directory traversal attempt
     * @throws \Cake\Http\Exception\NotFoundException When the view file could not e found or MissingTemplateException in debug mode
     */
    public function display(...$path): ?Response
    {
        //Disables the layout for `no-html` and `no-links` pages
        if (in_array(array_value_first($path), ['no-html', 'no-links'])) {
            $this->viewBuilder()->disableAutoLayout();
        }

        $count = count($path);
        if (!$count) {
            return $this->redirect('/');
        }
        if (in_array('..', $path, true) || in_array('.', $path, true)) {
            throw new ForbiddenException();
        }
        $page = $subpage = null;

        if (!empty($path[0])) {
            $page = $path[0];
        }
        if (!empty($path[1])) {
            $subpage = $path[1];
        }
        $this->set(compact('page', 'subpage'));

        try {
            return $this->render(implode('/', $path));
        } catch (MissingTemplateException $exception) {
            if (Configure::read('debug')) {
                throw $exception;
            }
            throw new NotFoundException();
        }
    }
}
