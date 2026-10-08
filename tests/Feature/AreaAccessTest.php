<?php

namespace Tests\Feature;

use App\Http\Middleware\SetEffectiveRole;
use App\Models\RsmUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class AreaAccessTest extends TestCase
{
    private function account(string $username = 'raffandy'): RsmUser
    {
        $user = new RsmUser(['username' => $username, 'role' => 'executive_director', 'area' => 'Regional A', 'is_active' => true]);
        $user->id = 75;
        $user->syncOriginal();

        return $user;
    }

    public function test_raffandy_can_switch_between_both_areas(): void
    {
        $user = $this->account();
        foreach (['Regional B', 'Regional A'] as $area) {
            $this->actingAs($user)->post('/area', ['area' => $area])
                ->assertRedirect('/')
                ->assertSessionHas('selected_area.75', $area);
        }
        $this->assertSame('executive_director', $user->role);
    }

    public function test_other_accounts_cannot_switch_areas(): void
    {
        $this->actingAs($this->account('other'))->post('/area', ['area' => 'Regional B'])->assertForbidden();
    }

    public function test_super_user_can_switch_between_both_areas(): void
    {
        $user = $this->account();
        $user->role = RsmUser::ROLE_SUPER_USER;
        $this->actingAs($user)->post('/area', ['area' => 'Regional B'])
            ->assertRedirect('/')
            ->assertSessionHas('selected_area.75', 'Regional B');
    }

    public function test_invalid_area_is_rejected(): void
    {
        $this->actingAs($this->account())->post('/area', ['area' => 'Regional C'])->assertSessionHasErrors('area');
    }

    public function test_selection_scopes_requests_without_dirtying_home_area(): void
    {
        foreach (['raffandy' => 'Regional B', 'other' => 'Regional A'] as $username => $expected) {
            $user = $this->account($username);
            Auth::setUser($user);
            $request = Request::create('/');
            $session = app('session.store');
            $session->put('selected_area.75', 'Regional B');
            $request->setLaravelSession($session);
            (new SetEffectiveRole)->handle($request, function () use ($user, $expected) {
                $this->assertSame($expected, $user->area);
                $this->assertFalse($user->isDirty('area'));

                return response('ok');
            });
            $this->assertSame('Regional A', $user->area);
        }
    }
}
