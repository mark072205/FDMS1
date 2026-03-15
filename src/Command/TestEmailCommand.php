<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

#[AsCommand(
    name: 'app:test-email',
    description: 'Send a test email to verify MAILER_DSN and Brevo SMTP configuration',
)]
class TestEmailCommand extends Command
{
    public function __construct(
        private MailerInterface $mailer,
        private string $fromAddress,
        private string $fromName,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('to', InputArgument::REQUIRED, 'Recipient email address');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $to = $input->getArgument('to');

        $email = (new Email())
            ->from(sprintf('%s <%s>', $this->fromName, $this->fromAddress))
            ->to($to)
            ->subject('Hi Baby')
            ->text('I LOVE YOU SO MUCH');

        try {
            $this->mailer->send($email);
            $io->success(sprintf('Test email sent to %s. Check inbox and spam folder.', $to));
            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error('Failed to send email: ' . $e->getMessage());
            $output->writeln('');
            $output->writeln('<comment>Full exception:</comment>');
            $output->writeln($e->getTraceAsString());
            $output->writeln('');
            $output->writeln('<comment>Brevo: use SMTP key (not API key) from SMTP & API settings. Port 587 with TLS.</comment>');
            return Command::FAILURE;
        }
    }
}
