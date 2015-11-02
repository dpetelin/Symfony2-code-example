<?php

namespace AppBundle\Service;

use Swift_Mailer;
use Swift_Message;
use Swift_Attachment;
use AppBundle\Exception\MailException;
use Symfony\Bundle\FrameworkBundle\Translation\Translator;
use Symfony\Bundle\FrameworkBundle\Templating\EngineInterface;

class MailerService
{
    /**
     * @var Swift_Mailer mailer
     */
    protected $mailer;

    /**
     * @var EngineInterface
     */
    protected $templating;

    /**
     * @var Translator
     */
    protected $translator;

    /**
     * @var array parameters
     */
    protected $parameters;

    /**
     * Mailer service constructor
     *
     * @param Swift_Mailer      $mailer     Mailer service
     * @param EngineInterface   $templating Templating engine
     * @param Translator        $translator Translator service
     * @param array             $parameters Mailer parameters
     */
    public function __construct(
        Swift_Mailer $mailer,
        EngineInterface $templating,
        Translator $translator,
        array $parameters
    ) {
        $this->mailer = $mailer;
        $this->templating = $templating;
        $this->translator = $translator;
        $this->parameters = $parameters;
    }

    /**
     * Parameter setter
     *
     * @param string $name  Parameter name
     * @param mixed  $value Parameter value
     *
     * @return $this
     */
    public function setParameter($name, $value)
    {
        $this->parameters[$name] = $value;

        return $this;
    }

    /**
     * Parameter getter
     *
     * @param string $name Parameter name
     *
     * @return mixed
     */
    public function getParameter($name)
    {
        if (isset($this->parameters[$name])) {
            return $this->parameters[$name];
        }

        return null;
    }

    /**
     * Send a message to $toEmail. Use the $template with the $data
     *
     * @param String $toEmail
     * @param String $template
     * @param array $data
     *
     * @return integer
     */
    public function send($toEmail, $template, array $data = array())
    {
        // get attachments
        $attachments = array();
        if (isset($data['attachments']) && is_array($data['attachments'])) {
            $attachments = $data['attachments'];
            unset($data['attachments']);
        }

        // get subject
        $subject = null;
        if (isset($data['subject'])) {
            $subject = $this->translator->trans($data['subject']);
            unset($data['subject']);
        }

        // render the template
        $body = $this->templating->render($template, $data);

        // create the message
        $message = \Swift_Message::newInstance()
            ->setSubject($subject)
            ->setFrom($this->getParameter('email'), $this->getParameter('name'))
            ->setTo($toEmail)
            ->setBody($body, $this->getParameter('contentType'), $this->getParameter('encoding'));

        // append attachments if needle
        if (! empty($attachments)) {
            $attachmentDefaults = array(
                'data'          => null,
                'path'          => null,
                'filename'      => null,
                'contentType'   => null,
            );

            foreach ($attachments as $key => $file) {
                $file = array_merge($attachmentDefaults, $file);
                $attachment = new Swift_Attachment($file['data'], $file['filename'], $file['contentType']);

                if (null == $file['data']) {
                    $attachment->setFile(
                        new \Swift_ByteStream_FileByteStream($file['path']),
                        $file['contentType']
                    );
                }

                // add it to the mail
                $message->attach($attachment);
            }
        }

        // send it
        $failedRecipients = null;
        try {
            $response = $this->mailer->send($message, $failedRecipients);
        } catch (\Exception $e) {
            $response = false;
            $this->handleError($e->getMessage());
        }

        if (! $response && is_array($failedRecipients)) {
            $this->handleError(
                'Could not sent emails to the following Recipients: ' .
                implode(', ', $failedRecipients) . '.'
            );
        }

        return $response;
    }

    /**
     * Report errors according to the config
     *
     * @param string $message
     *
     * @throws MailException
     */
    protected function handleError($message)
    {
        if ($this->parameters['errorType'] == 'none') {
            return;
        }

        if ($this->parameters['errorType'] == 'exception') {
            throw new MailException($message);
        }

        switch ($this->parameters['errorType']) {
            case 'error':
                $errorConstant = E_USER_ERROR;
                break;

            case 'warning':
                $errorConstant = E_USER_WARNING;
                break;

            default:
                $errorConstant = E_USER_NOTICE;
                break;
        }

        trigger_error($message, $errorConstant);
    }
}