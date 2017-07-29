<?php

namespace Tests;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use App\User;

class UserTest extends TestCase
{
    /**
     * All tests in this class are using the api
     *
     *=========================================*/

    /**
     * Helper function for creating users
     *
     */
    public function newUser($token = false, $returnPassword = false, $admin = false)
    {
        $passwordRaw = null;
        $JWTToken = null;
        
        if ($admin)
            $user = factory(User::class)->states('admin')->create();
        else
            $user = factory(User::class)->create();

        if ($token)
            $JWTToken = \JWTAuth::fromUser($user);
        if ($returnPassword)
            $passwordRaw = 'secret';
        // \JWTAuth::setToken($JWTToken);
        return (object) ['user' => $user, 'token' => $JWTToken, 'password' => $passwordRaw];
    }

    /**
     * @group loginExistingUserWithFacebook
     * Tests login with Facebook
     */
    public function testLoginAsExistingUserWithFacebook()
    {
        $user = $this->newUser(false, true);

        $response = $this->call('POST', '/api/auth/facebook', [
            'facebook_id' => $user->user->facebook_id,
            'name' => $user->user->name,
            'email' => $user->user->email,
            'gender' => $user->user->gender,
            'password' => $user->password
        ]);

        $response->assertJsonStructure([
            'token'
        ]);
    }

    /**
     * @group loginNewUserWithFacebook
     * Tests login with Facebook
     */
    public function testLoginAsNewUserWithFacebook()
    {
        $user = $this->newUser(false, true);

        $response = $this->call('POST', '/api/auth/facebook', [
            'facebook_id' => 2,
            'name' => 'Test',
            'email' => 'test@gmail.com',
            'gender' => 'male',
            'password' => 'test'
        ]);

        $response->assertJsonStructure([
            'token'
        ]);
    }

    /**
     * @group login
     * Tests login
     */
    public function testLogin()
    {
        $user = $this->newUser(false, true);

        $response = $this->call('POST', '/api/auth', [
            'email' => $user->user->email,
            'password' => $user->password
        ]);

        $response->assertJsonStructure([
            'token'
        ]);
    }

    /**
     * @group logout
     * Tests logout
     */
    public function testLogout()
    {
        $user = $this->newUser(false, true);

        $token = $this->call('POST', '/api/auth/facebook', [
            'facebook_id' => $user->user->facebook_id,
            'name' => $user->user->name,
            'email' => $user->user->email,
            'gender' => $user->user->gender,
            'password' => $user->password
        ])->getData()->token;

        $response = $this->callHttpWithToken('POST', '/api/logout', $token);

        $response->assertStatus(200);
    }

    /**
     * @group forgotPassword
     * Tests send token for reset password
     */
    public function testForgotPassword()
    {
        $user = factory(User::class)->create([
            'email' => 'peters945@hotmail.com'
        ]);

        \Mail::shouldReceive('send')->once()->andReturnUsing(function($view, $content) {
            $this->assertEquals('emails.forgot-password', $view);
        });

        $response = $this->call('POST', '/api/forgot-password', [
            'email' => $user->email
        ]);

        $response->assertStatus(200);
    }

    /**
     * @group resetPassword
     * Tests reset password
     */
    public function testResetPassword()
    {
        $user = factory(User::class)->create([
            'email' => 'peters945@hotmail.com'
        ]);

        $response = $this->call('POST', '/api/forgot-password', [
            'email' => $user->email
        ]);

        \Mail::shouldReceive('send')->once()->andReturnUsing(function($view, $content) {
            $this->assertEquals('emails.reset-password', $view);
        });

        $token = \App\PasswordReset::where('user_id', $user->id)->first()->token;

        $response = $this->call('POST', '/api/reset-password', [
            'token'    => $token,
            'password' => 'Test123'
        ]);

        $response->assertStatus(200);
    }
}
