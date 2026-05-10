# HolyC PHP
I just hack on this when I'm bored. If anyone wants to join in, feel free.
Eventually this is going to be a NASM Assembler for HolyC, written in PHP for the novelty.

No packages allowed! Everything must be written from scratch 
from the `Collections` to the `NodeTree` and everything in between!

## Status
- [x] `Collection` (map/filter/reduce/Iterator)
- [x] Lexer (numbers, hex, strings/chars w/ escapes, idents, keywords, operators, comments)
- [x] Parser / AST (Phase 1: expressions, statements, function decls, built-in types)
- [ ] Parser Phase 2 (user-defined types, multi-name decls, classes, switch, preprocessor)
- [ ] NASM codegen

## Run tests
```
php test.php
```
