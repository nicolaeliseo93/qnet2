<?php

namespace App\Notifications;

use App\DataObjects\Notifications\NotificationData;
use App\Enums\NotificationLevelEnum;
use App\Models\Note;
use App\Models\User;
use App\Notes\Mentions\MentionParser;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Sent to every NEW @mention on a note (spec 0052, D-11) — never to the
 * author, never twice for the same user/note. A DEDICATED class (not
 * GenericNotification) because it carries its own data (note, author, host
 * record label/link) and needs the `mail` channel on top of `database`.
 *
 * The database payload still goes through NotificationData (title, message,
 * level, action_url): the campanella, NotificationResource and the unread
 * counter work unchanged (constraints: no touching NotificationService/
 * NotificationResource). Since D-10 already guarantees the recipient can
 * read the host record, the message MAY carry a body excerpt without
 * over-disclosure.
 */
class NoteMentionNotification extends Notification implements ShouldQueue
{
    use Queueable;

    private const int EXCERPT_LENGTH = 140;

    public function __construct(
        private readonly Note $note,
        private readonly User $author,
        private readonly string $recordLabel,
        private readonly string $actionUrl,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * @return array{title: string|null, message: string|null, level: string, action_url: string|null}
     */
    public function toArray(object $notifiable): array
    {
        return (new NotificationData(
            title: __('You were mentioned'),
            message: $this->message(),
            level: NotificationLevelEnum::Info,
            actionUrl: $this->actionUrl,
        ))->toArray();
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('You were mentioned'))
            ->greeting(__('Hello :name', ['name' => $notifiable->name]))
            ->line($this->message())
            ->action(__('View'), $this->actionUrl);
    }

    private function message(): string
    {
        $namesById = $this->note->mentionedUsers->pluck('name', 'id')->all();
        $resolvedBody = MentionParser::resolveTokens($this->note->body, $namesById);
        $excerpt = Str::limit($resolvedBody, self::EXCERPT_LENGTH);

        return __(':author mentioned you in :label: :excerpt', [
            'author' => $this->author->name,
            'label' => $this->recordLabel,
            'excerpt' => $excerpt,
        ]);
    }
}
