// Recursive factorial. Compile and run with:
//   ./holyc run examples/Fact.HC ; echo $?
// Should print 120.

I64 fact(I64 n) {
  if (n < 2) return 1;
  return n * fact(n - 1);
}

U0 main() {
  return fact(5);
}
