<?php


namespace CRON\NeosCliTools\Command;


use CRON\NeosCliTools\Service\CRService;
use Exception;
use Neos\ContentRepository\Domain\Projection\Content\NodeInterface;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Cli\CommandController;


class NodeCommandController extends CommandController
{
    /**
     * @Flow\Inject
     * @var CRService
     */
    protected $cr;

    /**
     * Print a debug output of the given node
     *
     * @param NodeInterface $node
     */
    protected function printDebugNode(NodeInterface $node) {
        $data = [
            'identifier' => $node->getIdentifier(),
            'name' => $node->getName(),
            'type' => $node->getNodeType()->getName(),
            'isHidden' => $node->isHidden(),
            'properties' => $node->getProperties(),
        ];

        $this->outputLine(json_encode($data));
    }


    protected function dump(NodeInterface $node) {
        $this->printDebugNode($node);
    }

    /**
     * Dump the content of a specific node
     *
     * @param string $url URL of the node, e.g. '/news'
     * @param string $path full path of the specific node, e.g. '/sites/my-site/node..'
     * @param string $identifier Node identifier, e.g. 30fb88f3-36da-49c4-9dee-2a16dd01bf78
     * @param string $workspace workspace to use, e.g. 'user-admin', defaults to 'live'
     */
    public function dumpCommand($url = null, $path = null, $identifier = null, $workspace = 'live') {

        try {
            $this->cr->setup($workspace);

            /** @var NodeInterface $node */
            $node = null;

            if ($url !== null) {
                $node = $this->cr->getNodeForURL($url);
            } else if ($path !== null) {
                $node = $this->cr->getNodeForPath($path);
            } else if ($identifier !== null) {
                $node = $this->cr->context->getNodeByIdentifier($identifier);
            } else {
                throw new Exception(sprintf('At least --url, --path or --identifier must be supplied'));
            }

            if ($node === null) {
                throw new Exception('not found');
            }

            $this->dump($node);

        } catch (Exception $e) {
            $this->outputLine('ERROR: %s', [$e->getMessage()]);
        }
    }

}
