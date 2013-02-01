<?php

/*
 * This file is part of the Toyota Legacy PHP framework package.
 *
 * (c) Toyota Industrial Equipment <cyril.cottet@toyota-industries.eu>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Toyota\Component\Ldap\Tests\Core;

use Toyota\Component\Ldap\Tests\TestCase;
use Toyota\Component\Ldap\Core\DiffTracker;

class DiffTrackerTest extends TestCase
{
    /**
     * Tests basic tracking
     *
     * @return void
     */
    public function testBasicTracking()
    {
        $tracker = new DiffTracker();
        $this->assertDiff(
            $tracker,
            array(),
            array(),
            array(),
            false,
            'To begin with, no diff has been tracked yet'
        );

        $tracker->logAddition('test');
        $this->assertDiff(
            $tracker,
            array('test'),
            array(),
            array(),
            false,
            'Our first addition got tracked'
        );

        $tracker->logDeletion('other');
        $this->assertDiff(
            $tracker,
            array('test'),
            array('other'),
            array(),
            false,
            'Our first deletion got tracked'
        );

        $tracker->logAddition('new_test');
        $tracker->logDeletion('del_more');
        $this->assertDiff(
            $tracker,
            array('test', 'new_test'),
            array('other', 'del_more'),
            array(),
            false,
            'Additions & Deletions do pile up in the stacks'
        );

        $tracker->logAddition('test');
        $tracker->logDeletion('other');
        $this->assertDiff(
            $tracker,
            array('test', 'new_test'),
            array('other', 'del_more'),
            array(),
            false,
            'Diff duplications get ignored'
        );

        $tracker->logDeletion('test');
        $tracker->logAddition('test');
        $this->assertDiff(
            $tracker,
            array('new_test', 'test'),
            array('other', 'del_more'),
            array(),
            false,
            'Add + Del + Add = Add'
        );

        $tracker->logDeletion('test');
        $this->assertDiff(
            $tracker,
            array('new_test'),
            array('other', 'del_more'),
            array(),
            false,
            'Add + Del + Add + Del = None'
        );

        $tracker->logAddition('other');
        $tracker->logDeletion('other');
        $this->assertDiff(
            $tracker,
            array('new_test'),
            array('del_more','other'),
            array(),
            false,
            'Del + Add + Del = Del'
        );

        $tracker->logAddition('other');
        $this->assertDiff(
            $tracker,
            array('new_test'),
            array('del_more'),
            array('other'),
            false,
            'Del + Add + Del + Add = Rep'
        );
    }

    /**
     * Tests tracking replacements
     *
     * @return void
     */
    public function testReplacementsTracking()
    {

        $tracker = new DiffTracker();
        $tracker->logAddition('test');
        $tracker->logAddition('new_test');
        $tracker->logAddition('++');
        $tracker->logDeletion('del_more');
        $tracker->logDeletion('other');
        $tracker->logDeletion('--');

        $this->assertDiff(
            $tracker,
            array('test', 'new_test', '++'),
            array('del_more', 'other', '--'),
            array(),
            false,
            'Starting diff is as expected'
        );

        $tracker->logAddition('other');
        $this->assertDiff(
            $tracker,
            array('test', 'new_test', '++'),
            array('del_more', '--'),
            array('other'),
            false,
            'Adding a value which was removed results in a replacement'
        );

        $tracker->logAddition('other');
        $this->assertDiff(
            $tracker,
            array('test', 'new_test', '++'),
            array('del_more', '--'),
            array('other'),
            false,
            'Adding again a replaced item does not make a change'
        );

        $tracker->logDeletion('new_test');
        $this->assertDiff(
            $tracker,
            array('test', '++'),
            array('del_more', '--'),
            array('other'),
            false,
            'Deleting an added item results in simplification of the diff'
        );

        $tracker->logDeletion('new_test');
        $this->assertDiff(
            $tracker,
            array('test', '++'),
            array('del_more', '--'),
            array('other'),
            false,
            'Trying to delete again an item which was originally added is not a deletion'
        );

        $tracker->logDeletion('other');
        $this->assertDiff(
            $tracker,
            array('test', '++'),
            array('del_more', '--', 'other'),
            array(),
            false,
            'Deleting a replaced item is equivalent to deleting the original value'
        );

        $tracker->logDeletion('new_test');
        $this->assertDiff(
            $tracker,
            array('test', '++'),
            array('del_more', '--', 'other'),
            array(),
            false,
            'The deletion is still not registered until we reset tracking'
        );

        $tracker->reset();
        $this->assertDiff(
            $tracker,
            array(),
            array(),
            array(),
            false,
            'We are back with an empty diff as expected'
        );

        $tracker->logAddition('other');
        $tracker->logDeletion('new_test');
        $this->assertDiff(
            $tracker,
            array('other'),
            array('new_test'),
            array(),
            false,
            'Now basic addition & deletion behaviour are restored'
        );

        $tracker->logReplacement('repl1');
        $tracker->logReplacement('repl2');
        $tracker->logReplacement('repl3');
        $tracker->logReplacement('new_test');
        $tracker->logReplacement('other');
        $tracker->logDeletion('repl2');
        $tracker->logAddition('repl1');
        $this->assertDiff(
            $tracker,
            array(),
            array('repl2'),
            array('repl1', 'repl3', 'new_test', 'other'),
            false,
            'Direct replacements are also tracked as they should'
        );
    }

    /**
     * Tests object overriding
     *
     * @return void
     */
    public function testOverridesLogging()
    {
        $tracker = new DiffTracker();
        $tracker->logAddition('added');
        $tracker->logDeletion('deleted');
        $tracker->logReplacement('replaced');
        $this->assertDiff(
            $tracker,
            array('added'),
            array('deleted'),
            array('replaced'),
            false,
            'We start with basic diff tracking'
        );

        $tracker->markOverridden();
        $this->assertDiff(
            $tracker,
            array(),
            array(),
            array(),
            true,
            'Marking as overridden changes the flag and resets diff tracking'
        );
        $tracker->logAddition('added');
        $tracker->logDeletion('deleted');
        $tracker->logReplacement('replaced');
        $this->assertDiff(
            $tracker,
            array(),
            array(),
            array(),
            true,
            'An overriden item is not tracked anymore as its final state is the truth'
        );
        $tracker->reset();
        $tracker->logAddition('added');
        $tracker->logDeletion('deleted');
        $tracker->logReplacement('replaced');
        $this->assertDiff(
            $tracker,
            array('added'),
            array('deleted'),
            array('replaced'),
            false,
            'Diff tracking is active again'
        );
    }

    /**
     * Tests resetting
     *
     * @return void
     */
    public function testReset()
    {
        $tracker = new DiffTracker();
        $this->assertDiff(
            $tracker,
            array(),
            array(),
            array(),
            false,
            'To begin with, no diff has been tracked yet'
        );

        $tracker->reset();
        $this->assertDiff(
            $tracker,
            array(),
            array(),
            array(),
            false,
            'Snapshotting an empty diff does not make a change'
        );


        $tracker->logAddition('test');
        $tracker->logAddition('new_test');
        $tracker->logAddition('++');
        $tracker->logDeletion('del_more');
        $tracker->logDeletion('other');
        $tracker->logDeletion('--');
        $tracker->logAddition('other');
        $this->assertDiff(
            $tracker,
            array('test', 'new_test', '++'),
            array('del_more', '--'),
            array('other'),
            false,
            'Diff got tracked correctly'
        );

        $tracker->reset();
        $this->assertDiff(
            $tracker,
            array(),
            array(),
            array(),
            false,
            'Diff is back to its initial setup'
        );

        $tracker->markOverridden();
        $tracker->logAddition('test');
        $tracker->logAddition('new_test');
        $this->assertDiff(
            $tracker,
            array(),
            array(),
            array(),
            true,
            'We now have an object overridden with 2 values in its final state not tracked'
        );

        $tracker->reset();
        $this->assertDiff(
            $tracker,
            array(),
            array(),
            array(),
            false,
            'Even overriding flag is reset'
        );
    }

    /**
     * Asserts diff is as follows
     *
     * @param DiffTracker $tracker      Tracker being tested
     * @param array       $additions    Expected result for added items
     * @param array       $deletions    Expected result for removed items
     * @param array       $replacements Expected result for replaced items
     * @param boolean     $isOverridden Whether the complete object got overriden (Default: false)
     * @param string      $info         Logged message along with subsequent assertions (Optional)
     *
     * @return void
     */
    protected function assertDiff(
        DiffTracker $tracker,
        array $additions,
        array $deletions,
        array $replacements,
        $isOverridden = false,
        $info = null
    ) {
        $msg = (null === $info)?'':$info.' - ';

        $this->assertEquals(
            $additions,
            $tracker->getAdditions(),
            $msg . 'The expected additions are retrieved'
        );

        $this->assertEquals(
            $deletions,
            $tracker->getDeletions(),
            $msg . 'The expected deletions are retrieved'
        );

        $this->assertEquals(
            $replacements,
            $tracker->getReplacements(),
            $msg . 'The expected replacements are retrieved'
        );

        $this->assertEquals(
            $isOverridden,
            $tracker->isOverridden(),
            sprintf($msg . 'The object %s marked as overriden', $isOverridden?'is':'is not')
        );
    }

}