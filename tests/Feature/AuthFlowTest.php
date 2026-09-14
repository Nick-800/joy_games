<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('unauthenticated visitors are redirected to the login page', function () {
    $this->get('/')->assertRedirect('/login');
    $this->get('/login')->assertOk();
});

test('users can log in with valid credentials and reach the dashboard', function () {
    $user = User::factory()->admin()->create();

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect('/');

    $this->get('/')->assertOk();
});

test('users cannot log in with wrong password', function () {
    $user = User::factory()->create();

    $this->from('/login')->post('/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ])->assertRedirect('/login')->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('authenticated users can log out', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/logout')->assertRedirect('/login');

    $this->assertGuest();
});

test('authenticated user is redirected away from the login page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/login')->assertRedirect('/');
});
