<?php
namespace Tests\Feature;
use App\Models\{Company,User}; use Illuminate\Foundation\Testing\RefreshDatabase; use Tests\TestCase;
class AuthenticationTest extends TestCase { use RefreshDatabase;
 public function test_guests_are_redirected_to_login():void{$this->get('/dashboard')->assertRedirect('/login');}
 public function test_active_user_can_sign_in():void{$company=Company::factory()->create();$user=User::factory()->create(['company_id'=>$company->id,'password'=>'secret-password','active'=>true]);$this->post('/login',['email'=>$user->email,'password'=>'secret-password'])->assertRedirect('/dashboard');$this->assertAuthenticatedAs($user);}
}
