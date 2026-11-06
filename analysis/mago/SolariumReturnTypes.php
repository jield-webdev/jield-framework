<?php

// Solarium documents some concrete native returns as unions with their parents.
// Keep the enforced native return types so callers retain the concrete query/result API.
namespace Solarium\QueryType\Server\CoreAdmin\Query;

class Query
{
    /** @return \Solarium\QueryType\Server\CoreAdmin\Query\Action\Create */
    public function createCreate($options = []): \Solarium\QueryType\Server\CoreAdmin\Query\Action\Create
    {
    }
    /** @return \Solarium\QueryType\Server\CoreAdmin\Query\Action\MergeIndexes */
    public function createMergeIndexes($options = []): \Solarium\QueryType\Server\CoreAdmin\Query\Action\MergeIndexes
    {
    }
    /** @return \Solarium\QueryType\Server\CoreAdmin\Query\Action\Reload */
    public function createReload($options = []): \Solarium\QueryType\Server\CoreAdmin\Query\Action\Reload
    {
    }
    /** @return \Solarium\QueryType\Server\CoreAdmin\Query\Action\Rename */
    public function createRename($options = []): \Solarium\QueryType\Server\CoreAdmin\Query\Action\Rename
    {
    }
    /** @return \Solarium\QueryType\Server\CoreAdmin\Query\Action\RequestRecovery */
    public function createRequestRecovery($options = []): \Solarium\QueryType\Server\CoreAdmin\Query\Action\RequestRecovery
    {
    }
    /** @return \Solarium\QueryType\Server\CoreAdmin\Query\Action\RequestStatus */
    public function createRequestStatus($options = []): \Solarium\QueryType\Server\CoreAdmin\Query\Action\RequestStatus
    {
    }
    /** @return \Solarium\QueryType\Server\CoreAdmin\Query\Action\Split */
    public function createSplit($options = []): \Solarium\QueryType\Server\CoreAdmin\Query\Action\Split
    {
    }
    /** @return \Solarium\QueryType\Server\CoreAdmin\Query\Action\Status */
    public function createStatus($options = []): \Solarium\QueryType\Server\CoreAdmin\Query\Action\Status
    {
    }
    /** @return \Solarium\QueryType\Server\CoreAdmin\Query\Action\Swap */
    public function createSwap($options = []): \Solarium\QueryType\Server\CoreAdmin\Query\Action\Swap
    {
    }
    /** @return \Solarium\QueryType\Server\CoreAdmin\Query\Action\Unload */
    public function createUnload($options = []): \Solarium\QueryType\Server\CoreAdmin\Query\Action\Unload
    {
    }
}
namespace Solarium\QueryType\Server\Configsets\Query;

class Query
{
    /** @return \Solarium\QueryType\Server\Configsets\Query\Action\ListConfigsets */
    public function createList(array $options = []): \Solarium\QueryType\Server\Configsets\Query\Action\ListConfigsets
    {
    }
    /** @return \Solarium\QueryType\Server\Configsets\Query\Action\Upload */
    public function createUpload(array $options = []): \Solarium\QueryType\Server\Configsets\Query\Action\Upload
    {
    }
    /** @return \Solarium\QueryType\Server\Configsets\Query\Action\Create */
    public function createCreate(array $options = []): \Solarium\QueryType\Server\Configsets\Query\Action\Create
    {
    }
    /** @return \Solarium\QueryType\Server\Configsets\Query\Action\Delete */
    public function createDelete(array $options = []): \Solarium\QueryType\Server\Configsets\Query\Action\Delete
    {
    }
}
namespace Solarium\QueryType\Server\Collections\Query;

class Query
{
    /** @return \Solarium\QueryType\Server\Collections\Query\Action\Create */
    public function createCreate(array $options = []): \Solarium\QueryType\Server\Collections\Query\Action\Create
    {
    }
    /** @return \Solarium\QueryType\Server\Collections\Query\Action\Delete */
    public function createDelete(array $options = []): \Solarium\QueryType\Server\Collections\Query\Action\Delete
    {
    }
    /** @return \Solarium\QueryType\Server\Collections\Query\Action\Reload */
    public function createReload(array $options = []): \Solarium\QueryType\Server\Collections\Query\Action\Reload
    {
    }
    /** @return \Solarium\QueryType\Server\Collections\Query\Action\ClusterStatus */
    public function createClusterStatus(array $options = []): \Solarium\QueryType\Server\Collections\Query\Action\ClusterStatus
    {
    }
}
namespace Solarium\Core\Client;

class Client
{
    /** @return \Solarium\QueryType\Ping\Result */
    public function ping(\Solarium\Core\Query\QueryInterface $query, \Solarium\Core\Client\Endpoint|string|null $endpoint = null): \Solarium\QueryType\Ping\Result
    {
    }
    /** @return \Solarium\QueryType\Update\Result */
    public function update(\Solarium\Core\Query\QueryInterface $query, \Solarium\Core\Client\Endpoint|string|null $endpoint = null): \Solarium\QueryType\Update\Result
    {
    }
    /** @return \Solarium\QueryType\Select\Result\Result */
    public function select(\Solarium\Core\Query\QueryInterface $query, \Solarium\Core\Client\Endpoint|string|null $endpoint = null): \Solarium\QueryType\Select\Result\Result
    {
    }
    /** @return \Solarium\QueryType\MoreLikeThis\Result */
    public function moreLikeThis(\Solarium\Core\Query\QueryInterface $query, \Solarium\Core\Client\Endpoint|string|null $endpoint = null): \Solarium\QueryType\MoreLikeThis\Result
    {
    }
    /** @return \Solarium\QueryType\Terms\Result */
    public function terms(\Solarium\Core\Query\QueryInterface $query, \Solarium\Core\Client\Endpoint|string|null $endpoint = null): \Solarium\QueryType\Terms\Result
    {
    }
    /** @return \Solarium\QueryType\Spellcheck\Result\Result */
    public function spellcheck(\Solarium\Core\Query\QueryInterface $query, \Solarium\Core\Client\Endpoint|string|null $endpoint = null): \Solarium\QueryType\Spellcheck\Result\Result
    {
    }
    /** @return \Solarium\QueryType\Suggester\Result\Result */
    public function suggester(\Solarium\Core\Query\QueryInterface $query, \Solarium\Core\Client\Endpoint|string|null $endpoint = null): \Solarium\QueryType\Suggester\Result\Result
    {
    }
    /** @return \Solarium\QueryType\Extract\Result */
    public function extract(\Solarium\Core\Query\QueryInterface $query, \Solarium\Core\Client\Endpoint|string|null $endpoint = null): \Solarium\QueryType\Extract\Result
    {
    }
    /** @return \Solarium\QueryType\RealtimeGet\Result */
    public function realtimeGet(\Solarium\Core\Query\QueryInterface $query, \Solarium\Core\Client\Endpoint|string|null $endpoint = null): \Solarium\QueryType\RealtimeGet\Result
    {
    }
    /** @return \Solarium\QueryType\Luke\Result\Result */
    public function luke(\Solarium\Core\Query\QueryInterface $query, \Solarium\Core\Client\Endpoint|string|null $endpoint = null): \Solarium\QueryType\Luke\Result\Result
    {
    }
    /** @return \Solarium\QueryType\Server\CoreAdmin\Result\Result */
    public function coreAdmin(\Solarium\Core\Query\QueryInterface $query, \Solarium\Core\Client\Endpoint|string|null $endpoint = null): \Solarium\QueryType\Server\CoreAdmin\Result\Result
    {
    }
    /** @return \Solarium\QueryType\Select\Query\Query */
    public function createSelect(?array $options = null): \Solarium\QueryType\Select\Query\Query
    {
    }
    /** @return \Solarium\QueryType\MoreLikeThis\Query */
    public function createMoreLikeThis(?array $options = null): \Solarium\QueryType\MoreLikeThis\Query
    {
    }
    /** @return \Solarium\QueryType\Update\Query\Query */
    public function createUpdate(?array $options = null): \Solarium\QueryType\Update\Query\Query
    {
    }
    /** @return \Solarium\QueryType\Ping\Query */
    public function createPing(?array $options = null): \Solarium\QueryType\Ping\Query
    {
    }
    /** @return \Solarium\QueryType\Analysis\Query\Field */
    public function createAnalysisField(?array $options = null): \Solarium\QueryType\Analysis\Query\Field
    {
    }
    /** @return \Solarium\QueryType\Analysis\Query\Document */
    public function createAnalysisDocument(?array $options = null): \Solarium\QueryType\Analysis\Query\Document
    {
    }
    /** @return \Solarium\QueryType\Terms\Query */
    public function createTerms(?array $options = null): \Solarium\QueryType\Terms\Query
    {
    }
    /** @return \Solarium\QueryType\Spellcheck\Query */
    public function createSpellcheck(?array $options = null): \Solarium\QueryType\Spellcheck\Query
    {
    }
    /** @return \Solarium\QueryType\Suggester\Query */
    public function createSuggester(?array $options = null): \Solarium\QueryType\Suggester\Query
    {
    }
    /** @return \Solarium\QueryType\Extract\Query */
    public function createExtract(?array $options = null): \Solarium\QueryType\Extract\Query
    {
    }
    /** @return \Solarium\QueryType\Stream\Query */
    public function createStream(?array $options = null): \Solarium\QueryType\Stream\Query
    {
    }
    /** @return \Solarium\QueryType\Graph\Query */
    public function createGraph(?array $options = null): \Solarium\QueryType\Graph\Query
    {
    }
    /** @return \Solarium\QueryType\RealtimeGet\Query */
    public function createRealtimeGet(?array $options = null): \Solarium\QueryType\RealtimeGet\Query
    {
    }
    /** @return \Solarium\QueryType\Luke\Query */
    public function createLuke(?array $options = null): \Solarium\QueryType\Luke\Query
    {
    }
    /** @return \Solarium\QueryType\Server\CoreAdmin\Query\Query */
    public function createCoreAdmin(?array $options = null): \Solarium\QueryType\Server\CoreAdmin\Query\Query
    {
    }
    /** @return \Solarium\QueryType\Server\Collections\Query\Query */
    public function createCollections(?array $options = null): \Solarium\QueryType\Server\Collections\Query\Query
    {
    }
    /** @return \Solarium\QueryType\Server\Configsets\Query\Query */
    public function createConfigsets(?array $options = null): \Solarium\QueryType\Server\Configsets\Query\Query
    {
    }
    /** @return \Solarium\QueryType\Server\Api\Query */
    public function createApi(?array $options = null): \Solarium\QueryType\Server\Api\Query
    {
    }
    /** @return \Solarium\QueryType\ManagedResources\Query\Resources */
    public function createManagedResources(?array $options = null): \Solarium\QueryType\ManagedResources\Query\Resources
    {
    }
    /** @return \Solarium\QueryType\ManagedResources\Query\Stopwords */
    public function createManagedStopwords(?array $options = null): \Solarium\QueryType\ManagedResources\Query\Stopwords
    {
    }
    /** @return \Solarium\QueryType\ManagedResources\Query\Synonyms */
    public function createManagedSynonyms(?array $options = null): \Solarium\QueryType\ManagedResources\Query\Synonyms
    {
    }
}
