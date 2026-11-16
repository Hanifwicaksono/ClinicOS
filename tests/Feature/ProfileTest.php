<?php

namespace Tests\Feature;

use App\Models\Clinic;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/profile');

        $response
            ->assertOk()
            ->assertSeeVolt('profile.update-profile-information-form')
            ->assertSeeVolt('profile.update-password-form')
            ->assertSeeVolt('profile.delete-user-form');
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $component = Volt::test('profile.update-profile-information-form')
            ->set('name', 'Test User')
            ->set('email', 'test@example.com')
            ->call('updateProfileInformation');

        $component
            ->assertHasNoErrors()
            ->assertNoRedirect();

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $component = Volt::test('profile.update-profile-information-form')
            ->set('name', 'Test User')
            ->set('email', $user->email)
            ->call('updateProfileInformation');

        $component
            ->assertHasNoErrors()
            ->assertNoRedirect();

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_user_can_delete_their_account(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $component = Volt::test('profile.delete-user-form')
            ->set('password', 'password')
            ->call('deleteUser');

        $component
            ->assertHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $component = Volt::test('profile.delete-user-form')
            ->set('password', 'wrong-password')
            ->call('deleteUser');

        $component
            ->assertHasErrors('password')
            ->assertNoRedirect();

        $this->assertNotNull($user->fresh());
    }

    public function test_actual_clinic_ownership_blocks_self_deletion_without_logging_the_owner_out(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $clinic = Clinic::factory()->create();
        $owner = User::factory()->clinicOwner($clinic)->create();
        $this->actingAs($owner);
        Volt::test('profile.delete-user-form')->set('password', 'password')->call('deleteUser')
            ->assertHasErrors('password')->assertNoRedirect();
        $this->assertAuthenticatedAs($owner);
        $this->assertNotNull($owner->fresh());
        $this->assertSame($owner->id, $clinic->refresh()->owner_user_id);

        $owner->update(['account_type' => null]);
        Volt::test('profile.delete-user-form')->set('password', 'password')->call('deleteUser')
            ->assertHasErrors('password')->assertNoRedirect();
        $this->assertAuthenticatedAs($owner);
        $this->assertSame($owner->id, $clinic->refresh()->owner_user_id);
    }

    public function test_owner_candidate_without_a_clinic_can_delete_their_account(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);
        $candidate = User::factory()->clinicOwnerCandidate()->create();
        $this->actingAs($candidate);
        Volt::test('profile.delete-user-form')->set('password', 'password')->call('deleteUser')
            ->assertHasNoErrors()->assertRedirect('/');
        $this->assertGuest();
        $this->assertNull($candidate->fresh());
    }
}
