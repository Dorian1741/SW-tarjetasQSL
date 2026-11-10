"""Receptor SMTP local de pruebas. No reenvía mensajes a Internet."""
import asyncio
import pathlib
import sys

async def receive(reader, writer):
    writer.write(b'220 localhost ESMTP test\r\n')
    await writer.drain()
    while line := await reader.readline():
        command = line.decode().strip().upper()
        if command.startswith(('EHLO', 'HELO')):
            writer.write(b'250-localhost\r\n250 8BITMIME\r\n')
        elif command == 'DATA':
            writer.write(b'354 End with dot\r\n')
            await writer.drain()
            chunks = []
            while (part := await reader.readline()) not in (b'.\r\n', b''):
                chunks.append(part)
            with pathlib.Path(sys.argv[1]).open('ab') as stream:
                stream.write(b''.join(chunks))
            writer.write(b'250 Accepted\r\n')
        elif command == 'QUIT':
            writer.write(b'221 Bye\r\n')
            await writer.drain()
            break
        else:
            writer.write(b'250 OK\r\n')
        await writer.drain()
    writer.close()

async def main():
    server = await asyncio.start_server(receive, '127.0.0.1', 0)
    print(server.sockets[0].getsockname()[1], flush=True)
    await server.serve_forever()

asyncio.run(main())
