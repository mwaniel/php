<?php

class UsersController extends Controller
{
    /**
     * Handle the page request
     *
     * @param array $request the page parameters from a form post or query string
     */
    protected function handleRequest(&$request)
    {
        $user = $this->getUserSession();
        $this->assign('user', $user);

        $loginFields = Login::getFields();
        $this->assign('loginFields', $loginFields);

        $users = Login::queryRecords($this->pdo, array('sort' => 'first_name'));
        $this->assign('users', $users);
    }
}

?>
