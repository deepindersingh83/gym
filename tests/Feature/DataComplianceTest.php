<?php

namespace Tests\Feature;

use App\Enums\StaffRole;
use App\Filament\Gym\Pages\YourData;
use App\Filament\Gym\Resources\Members\Pages\ViewMember;
use App\Models\Gym;
use App\Models\Measurement;
use App\Models\SaasPayment;
use App\Models\SaasPlan;
use App\Services\Billing\GymBilling;
use App\Services\Compliance\GymClosure;
use App\Services\Compliance\GymDataExport;
use App\Services\Compliance\MemberData;
use App\Services\SubscriptionService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;
use ZipArchive;

class DataComplianceTest extends TestCase
{
    use RefreshDatabase;

    protected function inGymPanel(Gym $gym, $user): void
    {
        $this->actingAs($user);
        Filament::setCurrentPanel('gym');
        Filament::setTenant($gym);
    }

    public function test_owner_downloads_all_gym_data_as_a_zip_without_secrets(): void
    {
        Storage::fake('public');
        $gym = $this->createGym(['integrations' => ['whatsapp_token' => 'secret-token']]);
        $member = $this->createMember($gym, ['name' => 'Asha Rao']);
        Storage::disk('public')->put("members/{$gym->id}/asha.jpg", 'jpeg');

        $path = app(GymDataExport::class)->build($gym);
        $zip = new ZipArchive;
        $zip->open($path);

        $this->assertStringContainsString('Asha Rao', $zip->getFromName('members.csv'));
        $this->assertNotFalse($zip->locateName('payments.csv'));
        $this->assertNotFalse($zip->locateName('staff.csv'));
        $this->assertNotFalse($zip->locateName("files/members/{$gym->id}/asha.jpg"));
        $this->assertStringNotContainsString('integrations', $zip->getFromName('gym.csv'));
        $this->assertStringNotContainsString('secret-token', $zip->getFromName('gym.csv'));
        $zip->close();
        unlink($path);

        $this->inGymPanel($gym, $this->ownerOf($gym));
        Livewire::test(YourData::class)->callAction('download')->assertFileDownloaded();

        $this->inGymPanel($gym, $this->addStaff($gym, StaffRole::Manager));
        $this->get(YourData::getUrl(tenant: $gym))->assertForbidden();
        $this->assertSame($member->id, $member->fresh()->id);
    }

    public function test_member_data_download_and_erasure_keeps_payments(): void
    {
        $gym = $this->createGym();
        $member = $this->createMember($gym, ['name' => 'Asha Rao', 'email' => 'asha@example.com', 'medical_conditions' => 'Asthma']);
        $service = app(SubscriptionService::class);
        $service->recordPayment($service->create($member, $this->createPlan($gym, ['price' => 1000])), 1000, 'cash');
        Measurement::withoutGlobalScopes()->create(['gym_id' => $gym->id, 'member_id' => $member->id, 'recorded_on' => today(), 'weight_kg' => 70]);

        $export = app(MemberData::class)->export($member);
        $this->assertSame('Asha Rao', $export['member']['name']);
        $this->assertCount(1, $export['memberships']);
        $this->assertCount(1, $export['measurements']);

        $this->inGymPanel($gym, $this->ownerOf($gym));
        Livewire::test(ViewMember::class, ['record' => $member->getRouteKey()])
            ->callAction('downloadData')->assertFileDownloaded()
            ->callAction('erase', ['confirm' => 'ERASE'])->assertHasNoActionErrors();

        $member->refresh();
        $this->assertNotNull($member->erased_at);
        $this->assertStringStartsWith('Erased member', $member->name);
        $this->assertNull($member->email);
        $this->assertNull($member->medical_conditions);
        $this->assertSame(0, Measurement::withoutGlobalScopes()->where('member_id', $member->id)->count());
        $this->assertSame(1, $member->payments()->withoutGlobalScopes()->count()); // tax records stay

        // Receptionists cannot erase.
        $other = $this->createMember($gym);
        $this->inGymPanel($gym, $this->addStaff($gym, StaffRole::Receptionist));
        Livewire::test(ViewMember::class, ['record' => $other->getRouteKey()])->assertActionHidden('erase');
    }

    public function test_online_signup_records_consent(): void
    {
        $gym = $this->createGym(['settings' => ['allow_online_signup' => true]]);

        $this->post(route('join.store', $gym), [
            'name' => 'Neha', 'phone' => '9812345678', 'password' => 'secret-pass', 'consent' => '1',
        ])->assertRedirect();

        $member = $gym->members()->withoutGlobalScopes()->where('name', 'Neha')->sole();
        $this->assertNotNull($member->consented_at);
        $this->assertSame('online_signup', $member->consent_source);
    }

    public function test_closed_gyms_are_restorable_for_30_days_then_deleted_but_platform_invoices_stay(): void
    {
        Storage::fake('public');
        $gym = $this->createGym();
        $this->createMember($gym);
        app(SubscriptionService::class)->create($this->createMember($gym), $this->createPlan($gym));
        Storage::disk('public')->put("members/{$gym->id}/x.jpg", 'jpeg');
        $invoice = app(GymBilling::class)->recordPlanPayment($gym, SaasPlan::query()->first(), 'monthly', 999, ['gateway' => 'manual']);

        $closure = app(GymClosure::class);
        $closure->close($gym, 'Moving to paper');

        $this->assertSoftDeleted($gym);
        $this->assertSame(0, $closure->purgeDue());

        $closure->restore($gym);
        $this->assertNull($gym->fresh()->deletion_scheduled_at);

        $closure->close($gym->fresh());
        $this->travel(31)->days();
        $this->artisan('gym:purge-closed')->expectsOutput('Deleted 1 closed gym account.')->assertSuccessful();

        $this->assertNull(Gym::withTrashed()->find($gym->id));
        $this->assertSame(0, \DB::table('members')->where('gym_id', $gym->id)->count());
        Storage::disk('public')->assertMissing("members/{$gym->id}/x.jpg");

        $kept = SaasPayment::query()->find($invoice->id);
        $this->assertNotNull($kept);
        $this->assertNull($kept->gym_id);
        $this->assertSame('Iron Temple', $kept->invoice['buyer']['name']);
    }
}
