<?php
// This file is part of CodeRunner - http://coderunner.org.nz
//
// CodeRunner is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// CodeRunner is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with CodeRunner.  If not, see <http://www.gnu.org/licenses/>.

/**
 * An ordered chain of prototype layers, which resolves to a question's settings.
 *
 * @package   qtype_coderunner
 * @copyright 2026 Richard Lobb, The University of Canterbury
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace qtype_coderunner\v6;

/**
 * The layers a question's settings come from, in order from the root of the
 * prototype chain to the question's own overrides, and the resolution of them.
 *
 * The chain is walked once, by resolve(). Everything derived from it, the
 * resolved values, their provenance, and the per-layer lists for collected
 * fields, comes out of that one walk, so there is a single answer to which
 * layers were in play.
 *
 * The chain knows nothing about storage. Layers are built from the manager's
 * prototype records, from a question's overrides column, or by hand in a test.
 */
final class prototype_chain {
    /**
     * @var int The depth beyond which the prototype editor warns an author.
     * Nothing technical requires a limit, since resolved settings are cached.
     */
    const ADVISORY_MAX_DEPTH = 4;

    /** @var int A generous hard limit, to catch runaway data rather than deep designs. */
    const HARD_MAX_DEPTH = 20;

    /** @var override_set[] The layers, root first. */
    private array $layers;

    /**
     * @param override_set[] $layers the layers, root first: the root prototype,
     *      then each descendant, then the question's own overrides last.
     * @throws \coding_exception if the chain is too deep, if two layers share a
     *      source label, which is how a cycle shows up once the chain has been
     *      flattened into a list, or if any layer but the last supplies an
     *      instance field, since a prototype has no instance data.
     */
    public function __construct(array $layers) {
        $layers = array_values($layers);
        if (count($layers) > self::HARD_MAX_DEPTH) {
            throw new \coding_exception(
                'Prototype chain is ' . count($layers) . ' layers deep; the limit is ' . self::HARD_MAX_DEPTH . '.'
            );
        }
        $seen = [];
        foreach ($layers as $i => $layer) {
            if (!$layer instanceof override_set) {
                throw new \coding_exception("Layer $i of a prototype chain is not an override_set.");
            }
            if ($layer->source !== '' && isset($seen[$layer->source])) {
                throw new \coding_exception(
                    "Prototype chain visits '{$layer->source}' twice, so it contains a cycle."
                );
            }
            $seen[$layer->source] = true;

            // Only the last layer is the question. A prototype has no instance
            // data at all, so an instance field anywhere above it means the
            // chain was built wrongly, or a migrated prototype kept fields it
            // should have shed.
            if ($i < count($layers) - 1) {
                foreach ($layer->fields() as $name => $f) {
                    if (!$f->is_inheritable()) {
                        throw new \coding_exception(
                            "Layer '{$layer->source}' is a prototype but supplies the instance field '$name'."
                        );
                    }
                }
            }
        }
        $this->layers = $layers;
    }

    /**
     * The layers, root first.
     *
     * @return override_set[] the layers.
     */
    public function layers(): array {
        return $this->layers;
    }

    /**
     * How many layers the chain has, including the question's own overrides.
     *
     * @return int the depth.
     */
    public function depth(): int {
        return count($this->layers);
    }

    /**
     * Whether the chain is deeper than the editor should accept without warning.
     *
     * @return bool true if an author should be warned about the depth.
     */
    public function exceeds_advisory_depth(): bool {
        return $this->depth() > self::ADVISORY_MAX_DEPTH;
    }

    /**
     * The source labels of every layer, root first. This is what identifies the
     * chain for caching and for the version pinning recorded in an attempt.
     *
     * @return string[] the source labels.
     */
    public function source_labels(): array {
        return array_map(fn($layer) => $layer->source, $this->layers);
    }

    /**
     * Resolve the chain into the question's effective settings.
     *
     * Walks the layers once, root first. For a field that resolves to a single
     * value the last layer to supply it wins, and a field no layer supplies
     * takes the registry default. For a collected field every supplying layer
     * is recorded in order, because combining them needs work this class
     * cannot do: each layer's template parameters must be evaluated in that
     * layer's own language first, and a layer's UI parameters count only if
     * that layer's UI plugin matches the resolved one.
     *
     * A field present in a layer is an override even when its value is null or
     * the empty string, which is how a prototype's value is deliberately
     * blanked.
     *
     * @return question_settings the effective settings, with provenance.
     */
    public function resolve(): question_settings {
        $values = [];
        $sources = [];
        $collected = [];

        foreach (field_registry::all() as $name => $f) {
            if ($f->resolves_to_one_value()) {
                $values[$name] = $f->default;
            } else {
                $collected[$name] = [];
            }
        }

        foreach ($this->layers as $layer) {
            foreach ($layer->names() as $name) {
                $f = field_registry::get($name);
                if ($f->resolves_to_one_value()) {
                    $values[$name] = $layer->get($name);
                    $sources[$name] = $layer->source;
                } else {
                    $collected[$name][] = [
                        'value' => $layer->get($name),
                        'source' => $layer->source,
                        'layer' => $layer,
                    ];
                }
            }
        }

        return new question_settings($values, $sources, $collected, $this->source_labels());
    }

    /**
     * Build a chain from plain arrays, for tests and for callers that have
     * values rather than override sets.
     *
     * @param array $layers a list of [source label => array of field => value],
     *      or of arrays of field => value with numeric keys, root first.
     * @return self the chain.
     */
    public static function from_arrays(array $layers): self {
        $sets = [];
        foreach ($layers as $source => $values) {
            $sets[] = new override_set($values, is_string($source) ? $source : (string) $source);
        }
        return new self($sets);
    }
}
