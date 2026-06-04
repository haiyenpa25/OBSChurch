/**
 * Mock OBS WebSocket v5.x Server
 * Chạy trên port 4455 để giả lập OBS Studio phục vụ test.
 */

const { WebSocketServer } = require('ws');
const http = require('http');

const PORT = 4455;
const server = http.createServer((req, res) => res.end('Mock OBS WS'));
const wss = new WebSocketServer({ server });

wss.on('connection', (ws) => {
  console.log('[Mock OBS] Client connected');

  // Gửi Hello
  ws.send(JSON.stringify({
    op: 0,
    d: {
      obsVersion: '30.0.0',
      rpcVersion: 1
    }
  }));

  ws.on('message', (raw) => {
    try {
      const msg = JSON.parse(raw);
      console.log(`[Mock OBS] Received op: ${msg.op}`);
      
      if (msg.op === 1) { // Identify
        ws.send(JSON.stringify({ op: 2, d: { negotiateRpcVersion: 1 } }));
        console.log('[Mock OBS] Client Identified successfully');
        
        // Mock gửi event scene đổi sau 2 giây để test đồng bộ ngược
        setTimeout(() => {
          console.log('[Mock OBS] Simulating OBS Scene Change event -> Giang luan');
          ws.send(JSON.stringify({
            op: 5,
            d: {
              eventType: 'CurrentProgramSceneChanged',
              eventData: { sceneName: 'Giảng luận' }
            }
          }));
        }, 3000);
      }
      
      if (msg.op === 6) { // Request
        const { requestType, requestId } = msg.d;
        console.log(`[Mock OBS] Request: ${requestType}`);
        
        let responseData = {};
        if (requestType === 'GetCurrentProgramScene') {
          responseData = { sceneName: 'Chính lễ' };
        }
        
        ws.send(JSON.stringify({
          op: 7,
          d: {
            requestId,
            requestStatus: { result: true, code: 100 },
            responseData
          }
        }));
      }
    } catch (e) {
      console.error(e);
    }
  });

  ws.on('close', () => console.log('[Mock OBS] Client disconnected'));
});

server.listen(PORT, () => {
  console.log(`[Mock OBS] Server running at ws://localhost:${PORT}`);
});
