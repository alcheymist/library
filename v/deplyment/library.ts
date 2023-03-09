

interface library{
    database: Idatabase
}
interface database {
    get_sql_data(): void;
}

interface Idatabase{
    constructor: new (name: string) => database
    static_get_sql_data:()=>void
}

function test
    <
        lib extends library,
        classname extends keyof lib,
        $class extends lib[classname],
        Imethodname extends keyof $class,
        $constuctor extends Extract<$class[Imethodname], new (...args: any) => any>,
        R extends InstanceType<$constuctor>,
        cmethodname extends keyof R

    >(c: classname, d: Imethodname, b: cmethodname): void { super exec()};

const x=test("database","constructor",)