<?php

namespace LoggedCloud\PageStudio\Support;


/**
 * Evaluates a node graph: walks nodes in topological order, computes each
 * node's outputs from its inputs (edges) + settings, and returns the merged
 * context with every Output-node's named value added.
 *
 * Graph shape (the same shape the editor saves):
 *   $nodes = [
 *     ['id' => 'n1', 'type' => 'source.route_variable', 'settings' => ['variable_name' => 'userId'], 'position' => ['x' => 50, 'y' => 50]],
 *     ['id' => 'n2', 'type' => 'transform.uppercase',   'settings' => [],                          ...],
 *     ['id' => 'n3', 'type' => 'output',                'settings' => ['name' => 'screamingId'],  ...],
 *   ];
 *   $edges = [
 *     ['from_node' => 'n1', 'from_socket' => 'value', 'to_node' => 'n2', 'to_socket' => 'text'],
 *     ['from_node' => 'n2', 'from_socket' => 'value', 'to_node' => 'n3', 'to_socket' => 'value'],
 *   ];
 *
 * Cycles are detected and silently broken (the offending edge is skipped so
 * the rest of the graph still evaluates).
 */
class NodeGraphEngine
{
    public static function evaluate(array $nodes, array $edges, array $baseContext): array
    {
        return self::evaluateAll($nodes, $edges, $baseContext)['context'];
    }

    /**
     * Full evaluation result: the merged context AND the per-node output map.
     * The UI uses `nodeOutputs[nodeId][socketKey]` to show live values next to
     * each output socket.
     *
     * @return array{context: array, nodeOutputs: array<string, array<string, mixed>>}
     */
    /**
     * Per-request memoisation of evaluation results so the variables panel,
     * the live socket values, the canvas-decorated renderer, and the preview
     * pipeline don't all redo the same Bezier-of-work on every Livewire
     * round-trip.
     *
     * @var array<string, array{context: array, nodeOutputs: array}>
     */
    protected static array $cache = [];

    public static function flushCache(): void
    {
        self::$cache = [];
    }

    public static function evaluateAll(array $nodes, array $edges, array $baseContext): array
    {
        if (empty($nodes)) return ['context' => $baseContext, 'nodeOutputs' => []];

        // Best-effort cache key · graphs can contain DateTime / Eloquent values
        // in their context so we serialise carefully. On a serialise failure
        // we just bypass the cache · correctness over speed.
        try {
            $key = md5(serialize([$nodes, $edges, $baseContext]));
            if (isset(self::$cache[$key])) return self::$cache[$key];
        } catch (\Throwable) {
            $key = null;
        }

        $byId = [];
        foreach ($nodes as $n) {
            if (! empty($n['id'])) $byId[$n['id']] = $n;
        }

        // Adjacency · upstream[nodeId][socketKey] = ['from_node' => x, 'from_socket' => y]
        $upstream = [];
        foreach ($edges as $e) {
            if (empty($e['from_node']) || empty($e['to_node']) || empty($e['to_socket'])) continue;
            if (! isset($byId[$e['from_node']]) || ! isset($byId[$e['to_node']])) continue;
            $upstream[$e['to_node']][$e['to_socket']] = [
                'from_node'   => $e['from_node'],
                'from_socket' => $e['from_socket'] ?? 'value',
            ];
        }

        // Implicit edge · whenever a route_variable source reads a name that
        // an Output node also writes, treat that Output as an upstream so the
        // toposort evaluates them in the correct order. Lets graph authors
        // chain outputs without wiring explicit feedback edges.
        $orderingHints = [];
        $outputsByName = [];
        $consumersByName = [];
        foreach ($byId as $id => $node) {
            if (($node['type'] ?? '') === 'output') {
                $name = trim((string) ($node['settings']['name'] ?? ''));
                if ($name !== '') $outputsByName[$name][] = $id;
            } elseif (($node['type'] ?? '') === 'source.route_variable') {
                $name = trim((string) ($node['settings']['variable_name'] ?? ''));
                if ($name !== '') $consumersByName[$name][] = $id;
            }
        }
        foreach ($consumersByName as $name => $consumerIds) {
            if (! isset($outputsByName[$name])) continue;
            foreach ($consumerIds as $c) {
                foreach ($outputsByName[$name] as $producer) {
                    $orderingHints[$c][] = $producer;
                }
            }
        }

        $order = self::topologicalOrder($byId, $upstream, $orderingHints);
        $outputs = [];   // nodeId → socketKey → value
        $context = $baseContext;

        foreach ($order as $id) {
            $node = $byId[$id];
            $inputs = [];
            foreach ($upstream[$id] ?? [] as $socket => $link) {
                $inputs[$socket] = $outputs[$link['from_node']][$link['from_socket']] ?? null;
            }
            // Muted nodes act like a passthrough · the first input flows to
            // every output socket. Useful for temporarily bypassing a
            // transform without rewiring the graph.
            if (! empty($node['muted'])) {
                $schema = config('page-studio.nodes', [])[$node['type']] ?? [];
                $first  = $inputs[array_key_first($inputs) ?? ''] ?? null;
                $node_outputs = [];
                foreach (array_keys($schema['outputs'] ?? []) as $outKey) {
                    $node_outputs[$outKey] = $first;
                }
            } else {
                $node_outputs = self::evaluateNode($node, $inputs, $context);
            }
            $outputs[$id] = $node_outputs;

            if (($node['type'] ?? '') === 'output') {
                $name = trim((string) ($node['settings']['name'] ?? ''));
                if ($name !== '' && preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name)) {
                    $context[$name] = $node_outputs['value'] ?? null;
                }
            }
        }

        // Build a position-in-evaluation-order map so the editor can show
        // toposort badges on each node · 0-indexed, sources lowest.
        $orderMap = [];
        foreach ($order as $i => $id) $orderMap[$id] = $i;

        $result = ['context' => $context, 'nodeOutputs' => $outputs, 'order' => $orderMap];
        if ($key !== null) self::$cache[$key] = $result;
        return $result;
    }

    /**
     * @return array<string, mixed>  output socket key → value
     */
    protected static function evaluateNode(array $node, array $inputs, array $context): array
    {
        // Every built-in is now a NodeType class registered at boot.
        // evaluateCustom handles registry dispatch + the legacy template
        // fallback for any DB-stored custom node in one place.
        return self::evaluateCustom($node, $inputs, $context);
    }


    /**
     * Fallback evaluator · catches any node type registered at boot time by
     * the host app or by the user-defined custom-nodes table. The custom
     * library entries are merged into `page-studio.nodes` so we can look up
     * the template + socket schema here.
     */
    protected static function evaluateCustom(array $node, array $inputs, array $context = []): array
    {
        $type = $node['type'] ?? '';

        // Code-defined node · the registered NodeType subclass takes
        // precedence over any legacy template-based custom node sharing
        // the same key.
        if ($class = \LoggedCloud\PageStudio\Nodes\NodeRegistry::find($type)) {
            try {
                /** @var \LoggedCloud\PageStudio\Nodes\NodeType $instance */
                $instance = new $class();
                $outputs  = $instance->evaluate($inputs, $node['settings'] ?? [], $context);
                return is_array($outputs) ? $outputs : [];
            } catch (\Throwable) {
                return [];
            }
        }

        $library = config('page-studio.nodes', []);
        $schema = $library[$type] ?? null;
        if (! $schema || empty($schema['custom']) || ! isset($schema['template'])) return [];

        $template = (string) $schema['template'];
        $settings = $node['settings'] ?? [];

        // Substitute {{ inputs.X }} and {{ settings.X }} · also {{ X }} as a
        // shorthand that resolves to whichever socket / setting has that
        // name (inputs win when both exist).
        $value = preg_replace_callback(
            '/\{\{\s*(inputs|settings)?\.?([A-Za-z_][A-Za-z0-9_]*)\s*\}\}/',
            function ($m) use ($inputs, $settings) {
                $scope = $m[1] ?? '';
                $key   = $m[2];
                $resolved = match ($scope) {
                    'inputs'   => $inputs[$key]   ?? null,
                    'settings' => $settings[$key] ?? null,
                    default    => $inputs[$key]   ?? ($settings[$key] ?? null),
                };
                if (is_array($resolved) || is_object($resolved)) {
                    return is_object($resolved) && method_exists($resolved, '__toString')
                        ? (string) $resolved
                        : (string) json_encode($resolved);
                }
                return (string) ($resolved ?? '');
            },
            $template,
        );

        // Map the rendered string onto every declared output socket so the
        // graph keeps working when the custom node exposes multiple outputs.
        $outputs = [];
        foreach (array_keys($schema['outputs'] ?? ['value' => []]) as $socket) {
            $outputs[$socket] = $value;
        }
        return $outputs;
    }

    /**
     * Kahn's algorithm · returns the node IDs in dependency order. Nodes in
     * a cycle (or unreachable from any source) are appended at the end so
     * partial graphs still evaluate something.
     *
     * @return array<int, string>
     */
    protected static function topologicalOrder(array $byId, array $upstream, array $orderingHints = []): array
    {
        $indeg = [];
        $downstream = [];
        foreach ($byId as $id => $_) {
            $indeg[$id] = 0;
            $downstream[$id] = [];
        }
        foreach ($upstream as $to => $sockets) {
            foreach ($sockets as $link) {
                $from = $link['from_node'];
                if (! isset($downstream[$from])) continue;
                $downstream[$from][] = $to;
                $indeg[$to]++;
            }
        }
        // Layer in the synthetic Output → route_variable edges.
        foreach ($orderingHints as $to => $froms) {
            if (! isset($indeg[$to])) continue;
            foreach (array_unique($froms) as $from) {
                if (! isset($downstream[$from])) continue;
                if (in_array($to, $downstream[$from], true)) continue;
                $downstream[$from][] = $to;
                $indeg[$to]++;
            }
        }

        $queue = [];
        foreach ($indeg as $id => $deg) {
            if ($deg === 0) $queue[] = $id;
        }

        $order = [];
        while ($queue) {
            $id = array_shift($queue);
            $order[] = $id;
            foreach ($downstream[$id] as $next) {
                if (--$indeg[$next] === 0) $queue[] = $next;
            }
        }

        // Append nodes that didn't make the queue (cycles · unsatisfiable
        // dependencies) so an Output downstream of a cycle still evaluates,
        // even if its upstream values are null.
        foreach ($byId as $id => $_) {
            if (! in_array($id, $order, true)) $order[] = $id;
        }
        return $order;
    }
}
