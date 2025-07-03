<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CourseEndingSoonNotification extends Notification implements ShouldQueue // Implement ShouldQueue for background sending
{
    use Queueable;

    protected $courseName;
    protected $courseEndDate;
    protected $moodleUserEmail; // We need Moodle user's email to send notification

    /**
     * Create a new notification instance.
     *
     * @param string $courseName
     * @param string $courseEndDate Formatted end date
     * @param string $moodleUserEmail
     */
    public function __construct(string $courseName, string $courseEndDate, string $moodleUserEmail)
    {
        $this->courseName = $courseName;
        $this->courseEndDate = $courseEndDate;
        $this->moodleUserEmail = $moodleUserEmail;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable): array
    {
        return ['mail']; // Could also add 'database' later
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable): MailMessage
    {
        // $notifiable here would be the Moodle user (or a representation of them).
        // However, Laravel's default notification system expects an Eloquent model
        // that uses the Notifiable trait. Since Moodle users are external,
        // we might need a slightly different approach for sending.
        //
        // Option 1: Create a temporary "AnonymousNotifiable" or use `Notification::route()`
        // Option 2: If we sync Moodle users to a local table that uses Notifiable trait,
        //            then $notifiable could be that local user model.
        //
        // For now, let's use Notification::route() within the command that sends this.
        // This toMail method would then be called with a generic notifiable,
        // but the actual recipient email is handled by Notification::route().
        // Or, we can pass the email directly to the MailMessage if not using $notifiable.

        return (new MailMessage)
                    ->subject("Recordatorio: El curso '{$this->courseName}' está por finalizar")
                    ->greeting("Hola,") // Ideally, use user's name if available
                    ->line("Este es un recordatorio amistoso de que el curso '{$this->courseName}' en Moodle finalizará el {$this->courseEndDate}.")
                    ->line("Por favor, asegúrate de completar todas las actividades pendientes.")
                    ->action('Ir al Curso', url(config('moodle.base_url') . '/course/view.php?id=' . $notifiable->course_id_for_notification)) // $notifiable would need course_id
                    ->line('¡Gracias por usar nuestra plataforma!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toArray($notifiable): array
    {
        return [
            'course_name' => $this->courseName,
            'course_end_date' => $this->courseEndDate,
            'message' => "Recordatorio: El curso '{$this->courseName}' finaliza el {$this->courseEndDate}.",
        ];
    }
}
