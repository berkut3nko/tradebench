# Fast setup
## Run compose
```bash
docker compose build cpp-engine
docker compose run cpp-engine bash
```
## C++ setup
### Build
```bash
mkdir build && cd build
cmake ..
make
```
### How run tests
```bash
./run_tests
```
### Run core
```bash
./build/tradebench_core
```

## Exit docker
```bash
exit
``` 
`or Ctrl-D`