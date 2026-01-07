<?php
/**
 * Holds log data collected by Profiler_Console
 *
 * @var array{
 *     messages: array{
 *         log: list<array{data: string, type: 'log'}>|array{},
 *         memory: list<array{data: string, dataType: string, name: string, type: 'memory'}>|array{},
 *         error: list<array{data: string, file: string, line: int, type: 'error'}>|array{},
 *         speed: list<array{data: string, name: string, type: 'speed'}>|array{},
 *         benchmark: list<array{data: string, name: string, type: 'benchmark'}>|array{},
 *         all: list<array{data: string, type: 'log'}|array{data: string, dataType: string, name: string, type: 'memory'}|array{data: string, file: string, line: int, type: 'error'}|array{data: string, name: string, type: 'speed'}|array{data: string, name: string, type: 'benchmark'}>|array{},
 *     },
 *     files: array{name: string, bytes: int, size: string}[],
 *     fileTotals: array{size: string, largest: string},
 *     queries: array{
 *         sql: string,
 *         time: string,
 *         duplicate: bool,
 *         explain: array{possible_keys: string, key: string, type: string, rows: string}[]|null,
 *         profile: array{Status: string, Duration: string}[]|null
 *      }[],
 *     queryTotals: array{
 *         duplicates: int,
 *         time: string,
 *         select: array{total: int, time: string, percentage: float, time_percentage: float}|array{},
 *         insert: array{total: int, time: string, percentage: float, time_percentage: float}|array{},
 *         update: array{total: int, time: string, percentage: float, time_percentage: float}|array{},
 *         delete: array{total: int, time: string, percentage: float, time_percentage: float}|array{},
 *     },
 *     memoryTotals: array{used: string, total: string},
 *     speedTotals: array{total: string, allowed: string}
 * } $output
 */
?>
<style>
#profiler-container {
    position: fixed;
    bottom: 0;
    left: 0;
    width: 100%;
    display: none;
    justify-content: center;
    z-index: 9999;
    font-family: "Helvetica Neue", Arial, sans-serif;
    color: #DDDDDD;

    #profiler {
        width: 80%;
        display: flex;
        flex-flow: column nowrap;
        justify-content: center;
        background-color: #222222;
        border: 1rem solid #000000;
        border-top-left-radius: 1rem;
        border-top-right-radius: 1rem;
        border-bottom: none;

        var {
            display: block;
            margin-bottom: .25rem;
            font-size: 140%;
            font-weight: bold;
            font-style: normal;
        }

        h4 {
            color: #FFFFFF;
            font-size: x-small;
            font-weight: normal;
        }

        pre {
            margin: 0;
            padding: 0;
            white-space: pre-wrap;
            font-family: Consolas, monospace;
            font-size: inherit;
        }

        i {
            display: block;
            font-style: normal;
            padding-top: 0.2em;
            color: #AAAAAA;
        }

        b {
            font-weight: normal;
            color: #E6F387;
        }

        td {
            padding: 0.3rem;
            font-size: 0.7rem;
            background-color: #151515;
            border-left: 1px solid #333333;
            border-right: 1px solid #333333;
            border-bottom: 1px dotted #323232;
        }

        #profiler-metrics {
            display: flex;
            justify-content: space-around;
            width: 100%;
            background: #000000;

            .tab {
                max-height: 8rem;
                flex-grow: 1;
                flex-shrink: 1;
                flex-basis: 0;
                text-align: center;
                border: 1px solid #000000;
                border-bottom: 6px solid #444444;
                cursor: pointer;

                &.active {
                    border-bottom: 6px solid #FFFFFF;
                    background: #222;
                    cursor: default;
                }

                var {
                    font-size: 180%;
                }

                &#console var {
                    color: #588E13;
                }

                &#speed var {
                    color: #3769A0;
                }

                &#queries var {
                    color: #953FA1;
                }

                &#memory var {
                    color: #D28C00;
                }

                &#files var {
                    color: #B72F09;
                }
            }
        }
        .profiler-box {
            display: none;
            align-items: start;
            overflow-y: scroll;
            height: 14rem;

            &.tall {
                height: 25rem;
            }

            table {
                width: 100%;

                thead {
                    position: absolute !important;
                    height: 1px; width: 1px;
                    overflow: hidden;
                    clip: rect(1px, 1px, 1px, 1px);
                }

                td {
                    &.fit-content {
                        width: 1%;
                        white-space: nowrap;
                    }
                    &.profiler-log {
                        text-align: center;
                        text-transform: uppercase;
                    }
                }
            }

            .side {
                flex-grow: 0;
                flex-shrink: 0;
                flex-basis: 20%;

                & > div {
                    padding: 0.3rem 0;
                    --bg: #000000;
                    background-color: var(--bg);
                    text-align: center;

                    &:not([data-toggle]):nth-child(2n) {
                        background-color: color-mix(in srgb, var(--bg) 80%, #000000 20%) !important;
                    }

                    var {
                        color: #fff;
                    }
                }
            }

            &#profiler-console {
                .side {
                    display: flex;
                    flex-wrap: wrap;

                    & > div {
                        width: 50%;
                        background-color: var(--bg);
                        cursor: pointer;

                        &.selected {
                            background-color: color-mix(in srgb, var(--bg) 80%, #FFFFFF) !important;
                        }
                    }
                }
            }

            &#profiler-console .side > div[data-toggle=log],
            &#profiler-console td.profiler-log.log {
                --bg: #588E13;
                background-color: var(--bg);
            }

            &#profiler-console .side > div[data-toggle=error],
            &#profiler-console td.profiler-log.error,
            &#profiler-files .side > div {
                --bg: #B72F09;
                background-color: var(--bg);
            }

            &#profiler-console .side > div[data-toggle=memory],
            &#profiler-console td.profiler-log.memory,
            &#profiler-memory .side > div {
                --bg: #D28C00;
                background-color: var(--bg);
            }

            &#profiler-console .side > div[data-toggle=speed],
            &#profiler-console td.profiler-log.speed,
            &#profiler-speed .side > div {
                --bg: #3769A0;
                background-color: var(--bg);
            }

            &#profiler-console .side > div[data-toggle=benchmark],
            &#profiler-console td.profiler-log.benchmark {
                --bg: #84A360;
                background-color: var(--bg);
            }

            &#profiler-queries .side > div {
                --bg: #953FA1;
                background-color: var(--bg);
            }

            &#profiler-queries {
                tr {
                    border-left: 0.3rem solid transparent;

                    &.duplicate {
                        border-color: #B72F09;
                    }
                }

                b.duplicate {
                    color: #B72F09;
                }

                .query-profile div {
                    display: none;
                }
            }
        }

        #profiler-footer {
            display: flex;
            justify-content: space-between;
            background-color: #000000;
            border-top: 1px solid #ccc;
            font-size: smaller;

            a {
                margin-left: 0.4rem;
                cursor: pointer;
            }
        }
    }

    &:not(.closed) #profiler-boxes .profiler-box.active {
        display: flex;
    }

    &.closed {
        #profiler-footer {
            border-top: 1px dotted #444444;
        }

        #profiler-metrics .tab {
            max-height: fit-content;
            font-size: 75%;
        }
    }
}
</style>

<div id="profiler-container" class="closed">
    <div id="profiler">
        <div id="profiler-metrics">
            <div id="console" class="tab active">
                <var><?= count($output['messages']['all']) ?></var>
                <h4><?= __('Console') ?></h4>
            </div>
            <div id="speed" class="tab">
                <var><?= htmlspecialchars($output['speedTotals']['total']) ?></var>
                <h4><?= __('Load Time') ?></h4>
            </div>
            <div id="queries" class="tab">
                <var><?= htmlspecialchars(sprintf(_('%d Queries'), count($output['queries']))) ?></var>
                <h4><?= __('Database') ?></h4>
            </div>
            <div id="memory" class="tab">
                <var><?= htmlspecialchars($output['memoryTotals']['used']) ?></var>
                <h4><?= __('Memory Used') ?></h4>
            </div>
            <div id="files" class="tab">
                <var><?= htmlspecialchars(sprintf(_('%d Files'), count($output['files']))) ?></var>
                <h4><?= __('Included') ?></h4>
            </div>
        </div>

        <div id="profiler-boxes">
            <div id="profiler-console" class="profiler-box active">
                <?php if (count($output['messages']['all']) === 0): ?>
                    <h3><?= __('This panel has no log items') ?></h3>
                <?php else: ?>
                    <div class="side">
                        <div data-toggle="log">
                            <var><?= count($output['messages']['log']) ?></var>
                            <h4><?= __('Logs') ?></h4>
                        </div>
                        <div data-toggle="error">
                            <var><?= count($output['messages']['error']) ?></var>
                            <h4><?= __('Errors') ?></h4>
                        </div>
                        <div data-toggle="memory">
                            <var><?= count($output['messages']['memory']) ?></var>
                            <h4><?= __('Memory') ?></h4>
                        </div>
                        <div data-toggle="speed">
                            <var><?= count($output['messages']['speed']) ?></var>
                            <h4><?= __('Speed') ?></h4>
                        </div>
                        <div data-toggle="benchmark">
                            <var><?= count($output['messages']['benchmark']) ?></var>
                            <h4><?= __('Benchmarks') ?></h4>
                        </div>
                    </div>
                    <table>
                        <thead>
                        <tr>
                            <th><?= __('Type') ?></th>
                            <th><?= __('Details') ?></th>
                            <th><?= __('Data') ?></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach($output['messages']['all'] as $log): ?>
                            <tr data-logtype="<?= $log['type'] ?>">
                                <td class="profiler-log fit-content <?= $log['type'] ?>">
                                    <?= $log['type'] ?>
                                </td>
                                <?php switch($log['type']): case 'memory': ?>
                                <td>
                                    <?= htmlspecialchars($log['dataType']) ?>
                                    <?= htmlspecialchars($log['name']) ?>
                                </td>
                                <td class="fit-content">
                                    <b><?= htmlspecialchars($log['data']) ?></b>
                                </td>
                                <?php break ?>
                                <?php case 'speed': ?>
                                <?php case 'benchmark': ?>
                                <td>
                                    <?= htmlspecialchars($log['name']) ?>
                                </td>
                                <td class="fit-content">
                                    <b><?= htmlspecialchars($log['data']) ?></b>
                                </td>
                                <?php break ?>
                                <?php case 'error': ?>
                                <td>
                                    <pre><?= htmlspecialchars($log['file']) ?></pre>
                                    <i><?= htmlspecialchars(sprintf('Line %d', $log['line'])) ?></i>
                                </td>
                                <td class="fit-content">
                                    <?= htmlspecialchars($log['data']) ?>
                                </td>
                                <?php break ?>
                                <?php default: ?>
                                <td colspan="2">
                                    <pre><?= htmlspecialchars($log['data']) ?></pre>
                                </td>
                                <?php endswitch ?>
                            </tr>
                        <?php endforeach ?>
                        </tbody>
                    </table>
                <?php endif ?>
            </div>
            <div id="profiler-speed" class="profiler-box">
                <?php if (count($output['messages']['speed']) === 0): ?>
                    <h3><?= __('This panel has no log items') ?></h3>
                <?php else: ?>
                    <div class="side">
                        <div>
                            <var><?= htmlspecialchars($output['speedTotals']['total']) ?></var>
                            <h4><?= __('Load Time') ?></h4>
                        </div>
                        <div>
                            <var><?= htmlspecialchars($output['speedTotals']['allowed']) ?></var>
                            <h4><?= __('Max Execution Time') ?></h4>
                        </div>
                    </div>
                    <table>
                        <thead>
                        <tr>
                            <th><?= __('Location') ?></th>
                            <th><?= __('Time') ?></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($output['messages']['speed'] as $log): ?>
                        <tr>
                            <td>
                                <?= htmlspecialchars($log['name']) ?>
                            </td>
                            <td class="fit-content">
                                <b><?= htmlspecialchars($log['data']) ?></b>
                            </td>
                        </tr>
                        <?php endforeach ?>
                        </tbody>
                    </table>
                <?php endif ?>
            </div>
            <div id="profiler-queries" class="profiler-box">
                <?php if (count($output['queries']) === 0): ?>
                    <h3><?= __('This panel has no log items') ?></h3>
                <?php else: ?>
                    <div class="side">
                        <div>
                            <var><?= count($output['queries']) ?></var>
                            <h4><?= __('Total Queries') ?></h4>
                        </div>
                        <div>
                            <var><?= htmlspecialchars($output['queryTotals']['time']) ?></var>
                            <h4><?= __('Total Time') ?></h4>
                        </div>
                        <div>
                            <var><?= $output['queryTotals']['duplicates'] ?></var>
                            <h4><?= __('Duplicated Queries') ?></h4>
                        </div>
                        <div>
                            <?php if ($output['queryTotals']['select'] && $output['queryTotals']['select']['total'] > 0): ?>
                            <var><?= htmlspecialchars(sprintf(_('%d (%0.2f%%)'), $output['queryTotals']['select']['total'], $output['queryTotals']['select']['percentage'])) ?></var>
                            <var><?= htmlspecialchars(sprintf(_('%s (%0.2f%%)'), $output['queryTotals']['select']['time'], $output['queryTotals']['select']['time_percentage'])) ?></var>
                            <?php else: ?>
                            <var>0</var>
                            <?php endif ?>
                            <h4><?= __('Selects') ?></h4>
                        </div>
                        <div>
                            <?php if ($output['queryTotals']['update'] && $output['queryTotals']['update']['total'] > 0): ?>
                            <var><?= htmlspecialchars(sprintf(_('%d (%0.2f%%)'), $output['queryTotals']['update']['total'], $output['queryTotals']['update']['percentage'])) ?></var>
                            <var><?= htmlspecialchars(sprintf(_('%s (%0.2f%%)'), $output['queryTotals']['update']['time'], $output['queryTotals']['update']['time_percentage'])) ?></var>
                            <?php else: ?>
                            <var>0</var>
                            <?php endif ?>
                            <h4><?= __('Updates') ?></h4>
                        </div>
                        <div>
                            <?php if ($output['queryTotals']['insert'] && $output['queryTotals']['insert']['total'] > 0): ?>
                            <var><?= htmlspecialchars(sprintf(_('%d (%0.2f%%)'), $output['queryTotals']['insert']['total'], $output['queryTotals']['insert']['percentage'])) ?></var>
                            <var><?= htmlspecialchars(sprintf(_('%s (%0.2f%%)'), $output['queryTotals']['insert']['time'], $output['queryTotals']['insert']['time_percentage'])) ?></var>
                            <h4><?= __('Inserts') ?></h4>
                            <?php else: ?>
                            <var>0</var>
                            <?php endif ?>
                        </div>
                        <div>
                            <?php if ($output['queryTotals']['delete'] && $output['queryTotals']['delete']['total'] > 0): ?>
                            <var><?= htmlspecialchars(sprintf(_('%d (%0.2f%%)'), $output['queryTotals']['delete']['total'], $output['queryTotals']['delete']['percentage'])) ?></var>
                            <var><?= htmlspecialchars(sprintf(_('%s (%0.2f%%)'), $output['queryTotals']['delete']['time'], $output['queryTotals']['delete']['time_percentage'])) ?></var>
                            <?php else: ?>
                            <var>0</var>
                            <?php endif ?>
                            <h4><?= __('Deletes') ?></h4>
                        </div>
                    </div>
                    <table>
                        <thead>
                        <tr>
                            <th><?= __('Query Details') ?></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($output['queries'] as $query): ?>
                        <tr class="<?= $query['duplicate'] ? 'duplicate' : '' ?>">
                            <td>
                                <pre><?= htmlspecialchars($query['sql']) ?></pre>
                                <i>
                                <?php if ($query['duplicate']): ?>
                                    <b class="duplicate"><?= __('Duplicate query') ?></b> ·
                                <?php endif ?>
                                <?php if (isset($query['explain'])): ?>
                                    <?php foreach ($query['explain'] as $exp): ?>
                                    <?= __('Possible Keys:') ?>
                                    <b><?= htmlspecialchars($exp['possible_keys']) ?></b> ·
                                    <?= __('Keys Used:') ?>
                                    <b><?= htmlspecialchars($exp['key']) ?></b> ·
                                    <?= __('Type:') ?>
                                    <b><?= htmlspecialchars($exp['type']) ?></b> ·
                                    <?= __('Rows:') ?>
                                    <b><?= htmlspecialchars($exp['rows']) ?></b>
                                    <br/>
                                    <?php endforeach ?>
                                <?php endif ?>
                                <?php if (!empty($query['time'])): ?>
                                    <?= __('Speed:') ?>
                                    <b><?= htmlspecialchars($query['time']) ?></b>
                                <?php endif ?>
                                </i>
                                <?php if (is_array($query['profile'] ?? null)): ?>
                                <div class="query-profile">
                                    <h4>
                                        <a href="#" data-show="<?= __('Show Query Profile') ?>" data-hide="<?= __('Hide Query Profile') ?>">
                                            » <?= __('Show Query Profile') ?>
                                        </a>
                                    </h4>
                                    <div>
                                        <?php foreach ($query['profile'] as $line): ?>
                                        <i><?= htmlspecialchars($line['Status']) ?></i>
                                        <?= htmlspecialchars($line['Duration']) ?><br/>
                                        <?php endforeach ?>
                                    </div>
                                </div>
                                <?php endif ?>
                            </td>
                        </tr>
                        <?php endforeach ?>
                        </tbody>
                    </table>
                <?php endif ?>
            </div>
            <div id="profiler-memory" class="profiler-box">
                <?php if (count($output['messages']['memory']) === 0): ?>
                    <h3><?= __('This panel has no log items') ?></h3>
                <?php else: ?>
                    <div class="side">
                        <div>
                            <var><?= htmlspecialchars($output['memoryTotals']['used']) ?></var>
                            <h4><?= __('Used Memory') ?></h4>
                        </div>
                        <div>
                            <var><?= htmlspecialchars($output['memoryTotals']['total']) ?></var>
                            <h4><?= __('Total Available') ?></h4>
                        </div>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th><?= __('Type') ?></th>
                                <th><?= __('Name') ?></th>
                                <th><?= __('Size') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($output['messages']['memory'] as $log): ?>
                            <tr>
                                <td class="fit-content">
                                    <i><?= htmlspecialchars($log['dataType']) ?></i>
                                </td>
                                <td>
                                    <?= htmlspecialchars($log['name']) ?>
                                </td>
                                <td class="fit-content">
                                    <b><?= htmlspecialchars($log['data']) ?></b>
                                </td>
                            </tr>
                        <?php endforeach ?>
                        </tbody>
                    </table>
                <?php endif ?>
            </div>
            <div id="profiler-files" class="profiler-box">
                <?php if (count($output['files']) === 0): ?>
                    <h3><?= __('This panel has no log items') ?></h3>
                <?php else: ?>
                    <div class="side">
                        <div>
                            <var><?= count($output['files']) ?></var>
                            <h4><?= __('Total Files') ?></h4>
                        </div>
                        <div>
                            <var><?= htmlspecialchars($output['fileTotals']['size']) ?></var>
                            <h4><?= __('Total Size') ?></h4>
                        </div>
                        <div>
                            <var><?= htmlspecialchars($output['fileTotals']['largest']) ?></var>
                            <h4><?= __('Largest') ?></h4>
                        </div>
                    </div>
                    <table>
                        <thead>
                        <tr>
                            <th><?= __('File') ?></th>
                            <th><?= __('Size') ?></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($output['files'] as $file): ?>
                            <tr>
                                <td>
                                    <pre><?= htmlspecialchars($file['name']) ?></pre>
                                </td>
                                <td class="fit-content">
                                    <b><?= htmlspecialchars($file['size']) ?></b>
                                </td>
                            </tr>
                        <?php endforeach ?>
                        </tbody>
                    </table>
                <?php endif ?>
            </div>
        </div>
        <div id="profiler-footer">
            <div>
                <b>PHP</b>Profiler
            </div>
            <div>
                <a data-toggle="details"><?= __('Details') ?></a>
                <a data-toggle="height"><?= __('Toggle Height') ?></a>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const container = document.querySelector('div#profiler-container');
    if (!container) {
        return;
    }

    setTimeout(function () {
        container.style.display = 'flex';
    }, 10);

    document.querySelectorAll('.query-profile h4 a').forEach(function (el) {
        el.addEventListener('click', function () {
            let table = this.parent.querySelector('.query-profile div');
            if (table && table.style.display === 'none') {
                this.innerHTML = '» ' + this.dataset.hide
                table.style.display = 'block';
            } else {
                this.innerHTML = '» ' + this.dataset.show;
                table.style.display = 'none';
            }
        });
    });

    document.querySelector('#profiler-footer a[data-toggle=details]')?.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        const container = document.getElementById('profiler-container');
        if (!container.classList.toggle('closed')) {
            const act = document.querySelector("#profiler-boxes .active");
            if (!act) {
                document.querySelector('#profiler-metrics .tab.active')
                    .dispatchEvent(new MouseEvent('click'));
            }
        }
    });
    document.querySelector('#profiler-footer a[data-toggle=height]')?.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        document.querySelectorAll('.profiler-box').forEach(el => el.classList.toggle('tall'));
    });

    document.querySelectorAll('#profiler-metrics .tab').forEach(function (el) {
        el.addEventListener('click', function () {
            const target = this;
            document.querySelectorAll('#profiler-boxes > div')
                .forEach(el => el.style.display = el.id === `profiler-${target.id}` ? 'flex' : 'none');
            document.querySelectorAll('#profiler-metrics .tab').forEach(el => el.classList.remove('active'))
            target.classList.add('active');
            document.getElementById('profiler-container').classList.remove('closed');
        });
    });

    document.querySelectorAll('#profiler-console .side > div').forEach(function (el) {
        if (parseInt(el.querySelector('var')?.innerHTML, 10) === 0) {
            return;
        }

        el.addEventListener('click', function() {
            const target = this;
            const log_type = this.dataset.toggle;
            const selected = this.classList.contains('selected');
            document.querySelectorAll('#profiler-console tbody tr')
                .forEach(el => el.style.display = (el.dataset.logtype === log_type || selected) ? 'table-row' : 'none');
            document.querySelectorAll('#profiler-console .side > div')
                .forEach(el => el !== target && el.classList.remove('selected'));

            this.classList.toggle('selected');
        });
    });
});
</script>
