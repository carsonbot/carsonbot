<?php

namespace App\Subscriber;

use App\Api\Status\Status;
use App\Api\Status\StatusApi;
use App\Event\GitHubEvent;
use App\GitHubEvents;
use Psr\Log\LoggerInterface;

class StatusChangeByCommentSubscriber extends AbstractStatusChangeSubscriber
{
    private $logger;

    public function __construct(StatusApi $statusApi, LoggerInterface $logger)
    {
        parent::__construct($statusApi);
        $this->logger = $logger;
    }

    /**
     * Parses the text of the comment and looks for keywords to see
     * if this should cause any status change.
     */
    public function onIssueComment(GitHubEvent $event)
    {
        $data = $event->getData();
        $repository = $event->getRepository();
        $issueNumber = $data['issue']['number'];
        $newStatus = $this->parseStatusFromText($data['comment']['body']);

        if (Status::REVIEWED === $newStatus && false === $this->isUserAllowedToReview($data)) {
            $newStatus = null;
        }

        $event->setResponseData([
            'issue' => $issueNumber,
            'status_change' => $newStatus,
        ]);

        if (null === $newStatus) {
            return;
        }

        $this->logger->debug(sprintf('Setting issue number %s to status %s', $issueNumber, $newStatus));
        $this->statusApi->setIssueStatus($issueNumber, $newStatus, $repository);
    }

    public static function getSubscribedEvents()
    {
        return [
            GitHubEvents::ISSUE_COMMENT => 'onIssueComment',
        ];
    }

    private function isUserAllowedToReview(array $data)
    {
        return $data['issue']['user']['login'] !== $data['comment']['user']['login'];
    }
}
