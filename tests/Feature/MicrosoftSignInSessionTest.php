<?php
namespace Tests\Feature;
use Tests\TestCase;use Illuminate\Foundation\Testing\RefreshDatabase;use App\Models\User;use Laravel\Socialite\Facades\Socialite;use Laravel\Socialite\Two\InvalidStateException;use Mockery;
class MicrosoftSignInSessionTest extends TestCase {
 use RefreshDatabase;
 protected function setUp():void{parent::setUp();config(['services.azure.client_id'=>'test-id','services.azure.client_secret'=>'test-secret','services.azure.tenant'=>'tenant','services.azure.redirect'=>'http://localhost:8000/api/auth/microsoft/callback']);}
 public function test_start_moves_to_callback_origin_before_creating_oauth_state():void{
  Socialite::shouldReceive('driver')->never();$this->get('http://127.0.0.1:8000/auth/azure')->assertRedirect('http://localhost:8000/auth/azure');
 }
 public function test_matching_origin_starts_stateful_provider_redirect():void{
  $provider=Mockery::mock();$provider->shouldReceive('redirect')->once()->andReturn(redirect('https://login.microsoftonline.com/test'));Socialite::shouldReceive('driver')->with('azure')->once()->andReturn($provider);
  $this->get('http://localhost:8000/auth/azure')->assertRedirect('https://login.microsoftonline.com/test');
 }
 public function test_invalid_state_returns_a_clear_retry_message_without_authenticating():void{
  $provider=Mockery::mock();$provider->shouldReceive('user')->once()->andThrow(new InvalidStateException);Socialite::shouldReceive('driver')->with('azure')->once()->andReturn($provider);
  $this->get('http://localhost:8000/api/auth/microsoft/callback?code=test&state=bad')->assertRedirect('/login')->assertSessionHasErrors('email');$this->assertGuest();
 }
 public function test_existing_account_logs_in_and_stays_on_the_callback_host():void{
  $user=User::create(['name'=>'Work user','email'=>'work@example.test','password'=>'secret123','system_role'=>'user','account_status'=>'active','must_change_password'=>false]);
  $remote=new \Laravel\Socialite\Two\User;$remote->map(['id'=>'azure-object','name'=>'Work user','email'=>'work@example.test']);$provider=Mockery::mock();$provider->shouldReceive('user')->once()->andReturn($remote);Socialite::shouldReceive('driver')->with('azure')->once()->andReturn($provider);
  $this->get('http://localhost:8000/api/auth/microsoft/callback?code=test&state=valid')->assertRedirect('/dashboard')->assertSessionHas('auth_method','azure');$this->assertAuthenticatedAs($user);$this->assertDatabaseHas('social_accounts',['user_id'=>$user->id,'provider_user_id'=>'azure-object']);
 }
 public function test_unregistered_account_is_not_created_or_logged_in():void{
  $remote=new \Laravel\Socialite\Two\User;$remote->map(['id'=>'unknown-object','email'=>'unknown@example.test']);$provider=Mockery::mock();$provider->shouldReceive('user')->andReturn($remote);Socialite::shouldReceive('driver')->with('azure')->andReturn($provider);
  $this->get('http://localhost:8000/api/auth/microsoft/callback')->assertRedirect('/login')->assertSessionHasErrors('email');$this->assertGuest();$this->assertDatabaseMissing('users',['email'=>'unknown@example.test']);
 }
}
