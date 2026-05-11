# HolyC PHP
I just hack on this when I'm bored. If anyone wants to join in, feel free.
Eventually this is going to be a NASM Assembler for HolyC, written in PHP for the novelty.

No packages allowed! Everything must be written from scratch 
from the `Collections` to the `NodeTree` and everything in between!

## Status
- [x] `Collection` (map/filter/reduce/Iterator)
- [x] Lexer (numbers, hex, strings/chars w/ escapes, idents, keywords, operators, comments)
- [x] Parser / AST Phase 1 (expressions, statements, function decls, built-in types)
- [x] Parser / AST Phase 2 (user-defined types, multi-name decls, classes, top-level stmts, full compound-assign + `!`)
- [x] NASM codegen — walking skeleton (System V AMD64 / Linux): functions, calls, locals, arithmetic, comparisons, if/else, while, for, break, recursion
- [ ] Parser Phase 3 (`switch`/`case`, `try`/`catch`/`throw`, preprocessor expansion)
- [ ] Strings, pointers, arrays, structs in codegen
- [ ] HolyC `Print` / format-string builtin

## Compile and run a program
```
./holyc run examples/Fact.HC ; echo $?      # -> 120
./holyc compile examples/Fact.HC -o fact.s  # emit .asm
./holyc build   examples/Fact.HC -o fact    # build a binary
./holyc parse   examples/Fact.HC            # dump the AST
./holyc lex     examples/Fact.HC            # dump the token stream
```

Requires `nasm` and `ld` on `$PATH` for `build`/`run`.

## Run tests
```
php test.php                       # run everything
php test.php --filter=class        # only run tests whose name contains "class"
php test.php --no-color            # disable ANSI colours
```

Tests live in `tests/` and are organised by area: `CollectionTest`, `LexerTest`,
`ParserTest`, `IntegrationTest`. The runner in `test.php` discovers `test*`
methods on each suite and reports a `Suite::method  (file:line)` location for
any failures, with aligned expected/actual values.
