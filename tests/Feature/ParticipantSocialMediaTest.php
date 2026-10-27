<?php

namespace Tests\Feature;

use App\Models\Participant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ParticipantSocialMediaTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_save_a_participant_social_media_platform(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)
            ->post('/participants', [
                'name' => 'Kai Example',
                'username' => 'kai_example',
                'social_media' => 'TikTok',
                'priority' => 0,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('participants', [
            'username' => 'kai_example',
            'social_media' => 'TikTok',
        ]);
    }

    public function test_username_with_a_period_is_accepted(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)
            ->from('/participants')
            ->post('/participants', [
                'name' => 'Mona Amertz',
                'username' => 'mona.amertz',
                'social_media' => 'Instagram',
                'priority' => 0,
            ])
            ->assertRedirect('/participants')
            ->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('participants', ['username' => 'mona.amertz']);
    }

    public function test_csv_import_saves_the_optional_social_media_column(): void
    {
        $admin = User::factory()->create();
        $file = UploadedFile::fake()->createWithContent('roster.csv', "username,name,social_media,priority\nlee,Lee Example,Instagram,0\n");

        $this->actingAs($admin)
            ->post('/participants/import', ['file' => $file])
            ->assertRedirect();

        $this->assertDatabaseHas('participants', [
            'username' => 'lee',
            'social_media' => 'Instagram',
        ]);
    }

    public function test_csv_without_social_media_column_preserves_existing_platform_values(): void
    {
        $admin = User::factory()->create();
        Participant::factory()->create(['username' => 'keep_platform', 'social_media' => 'TikTok']);
        $file = UploadedFile::fake()->createWithContent('roster.csv', "username,name,priority\nkeep_platform,Updated Name,0\n");

        $this->actingAs($admin)
            ->post('/participants/import', ['file' => $file])
            ->assertRedirect();

        $this->assertDatabaseHas('participants', [
            'username' => 'keep_platform',
            'social_media' => 'TikTok',
        ]);
    }
}
