<?php

namespace Tests\Feature;

use App\Filament\Gym\Resources\Members\Pages\CreateMember;
use App\Models\Member;
use App\Support\PhotoStore;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class CameraPhotoTest extends TestCase
{
    use RefreshDatabase;

    /** A real PNG, as the browser would send after taking a photo. */
    protected function photoDataUrl(int $width = 1600, int $height = 1200): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, 30, 160, 90));
        ob_start();
        imagepng($image);

        return 'data:image/png;base64,'.base64_encode(ob_get_clean());
    }

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    public function test_photos_are_resized_and_saved_as_jpeg(): void
    {
        $path = PhotoStore::store($this->photoDataUrl(), 'members/1');

        Storage::disk('public')->assertExists($path);
        [$w, $h, $type] = getimagesizefromstring(Storage::disk('public')->get($path));
        $this->assertSame([1000, 750, IMAGETYPE_JPEG], [$w, $h, $type]);
        $this->assertStringStartsWith('members/1/', $path);
    }

    public function test_fake_or_broken_images_are_rejected(): void
    {
        $this->assertFalse(PhotoStore::isValidImage('data:image/png;base64,'.base64_encode('<?php echo "hi";')));
        $this->assertFalse(PhotoStore::isValidImage('data:image/svg+xml;base64,'.base64_encode('<svg/>')));
        $this->assertFalse(PhotoStore::isValidImage('data:image/jpeg;base64,not-base64!!'));
        $this->assertTrue(PhotoStore::isValidImage($this->photoDataUrl(10, 10)));
    }

    public function test_staff_take_a_member_photo_with_the_camera(): void
    {
        $gym = $this->createGym();
        $this->actingAs($this->ownerOf($gym));
        Filament::setCurrentPanel('gym');
        Filament::setTenant($gym);

        Livewire::test(CreateMember::class)
            ->fillForm(['name' => 'Photo Person', 'phone' => '9800000001', 'photo_path' => $this->photoDataUrl(400, 400)])
            ->call('create')
            ->assertHasNoFormErrors();

        $member = Member::query()->where('phone', '9800000001')->sole();
        $this->assertStringStartsWith("members/{$gym->id}/", $member->photo_path);
        Storage::disk('public')->assertExists($member->photo_path);

        Livewire::test(CreateMember::class)
            ->fillForm(['name' => 'Bad Photo', 'phone' => '9800000002', 'photo_path' => 'data:image/png;base64,'.base64_encode('nope')])
            ->call('create')
            ->assertHasFormErrors(['photo_path']);
    }

    public function test_public_signup_accepts_a_camera_photo(): void
    {
        $gym = $this->createGym();

        $this->post(route('join.store', $gym), [
            'name' => 'Selfie Joiner', 'phone' => '9811100055', 'email' => 'selfie@example.com', 'password' => 'secret-pass',
            'photo_data' => $this->photoDataUrl(800, 800), 'consent' => '1',
        ])->assertRedirect();

        $member = Member::withoutGlobalScopes()->where('phone', '9811100055')->sole();
        Storage::disk('public')->assertExists($member->photo_path);
    }
}
