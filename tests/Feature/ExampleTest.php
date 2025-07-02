<?php

it('redirects unauthenticated users from root to sign-in', function () {
    $response = $this->get('/');

    $response->assertStatus(302);
    $response->assertRedirect(route('login')); // 'login' is the typical name for sign-in route
});
