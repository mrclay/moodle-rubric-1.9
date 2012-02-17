###About

This is the [Rubric mod](http://moodle.org/mod/forum/discuss.php?d=92646&parent=445584) updated for Moodle 1.9.16+ (Build: 20120202).

This will be updated as new Moodle 1.9.x releases are made. Each commit will be tagged with the compatible Moodle version.

###History

[brki](https://github.com/brki) merged changes from 1.9.16 using this sequence:

    mkdir tmp && cd tmp
    git clone git://git.moodle.org/moodle.git
    git clone git://github.com/mrclay/moodle-rubric-1.9.git
    cd moodle
    git checkout -b rubric origin/MOODLE_19_STABLE
    git reset --hard v1.9.12
    rm -rf mod/assignment
    cp -R ../moodle-rubric-1.9/mod/assignment mod/
    git merge origin/MOODLE_19_STABLE
    cd ../moodle-rubric-1.9.git
    git checkout -b rubric-1.9.16
    rm -rf mod/assignment
    cp -R ../moodle/mod/assignment mod/
    git diff
    git commit

Steve Clay carefully merged in the changes to Moodle's core assignment mod made between 1.9.1 and 1.9.12, [Nigel Pegram's patch](https://github.com/mrclay/moodle-rubric-1.9/commit/f6e3a3e8f4e22af7835754e8579f02a855c3a29c#L17R43), and a [PHP5.3 bug fix](https://github.com/mrclay/moodle-rubric-1.9/commit/f6e3a3e8f4e22af7835754e8579f02a855c3a29c#L6R23).

Spencer Creasey created the original rubric mod based on mod/assignment from Moodle 1.9.1 (or thereabouts).